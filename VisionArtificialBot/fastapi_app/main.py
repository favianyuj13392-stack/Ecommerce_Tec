from fastapi import FastAPI, UploadFile, File, WebSocket, WebSocketDisconnect
import uvicorn
import cv2
import numpy as np
import json
from detection import process_frame

app = FastAPI(title="Vision Artificial Bot")

@app.post("/detect")
async def detect_people(file: UploadFile = File(...)):
    contents = await file.read()
    nparr = np.frombuffer(contents, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

    if img is None:
        return {"error": "Invalid image"}

    results = process_frame(img)
    return {"results": results}

@app.websocket("/ws/detect")
async def websocket_endpoint(websocket: WebSocket):
    await websocket.accept()
    try:
        while True:
            # Receive bytes from the client (webcam frame)
            data = await websocket.receive_bytes()

            # Decode the image
            nparr = np.frombuffer(data, np.uint8)
            img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

            if img is not None:
                # Process the frame
                results = process_frame(img)
                # Send the detection and proximity data back
                await websocket.send_json({"results": results})
            else:
                await websocket.send_json({"error": "Failed to decode image"})

    except WebSocketDisconnect:
        print("Client disconnected")
    except Exception as e:
        print(f"Error in websocket: {e}")

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
