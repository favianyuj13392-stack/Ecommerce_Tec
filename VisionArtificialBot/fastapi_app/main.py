from fastapi import FastAPI, UploadFile, File, BackgroundTasks
import uvicorn
import cv2
import numpy as np
from detection import process_frame

app = FastAPI(title="Vision Artificial Bot")

@app.post("/detect")
async def detect_people(file: UploadFile = File(...)):
    # Read image
    contents = await file.read()
    nparr = np.frombuffer(contents, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

    if img is None:
        return {"error": "Invalid image"}

    # Process frame
    results = process_frame(img)
    return {"results": results}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
