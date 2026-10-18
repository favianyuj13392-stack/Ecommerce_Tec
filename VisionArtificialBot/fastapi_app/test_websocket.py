import cv2
import numpy as np
from fastapi.testclient import TestClient
from main import app

client = TestClient(app)

def test_websocket_detection():
    # Create a dummy test image (black square)
    img = np.zeros((480, 640, 3), dtype=np.uint8)

    # Encode as JPEG bytes
    success, encoded_img = cv2.imencode('.jpg', img)
    if not success:
        print("Failed to encode image")
        return

    img_bytes = encoded_img.tobytes()

    # Test WebSocket connection
    with client.websocket_connect("/ws/detect") as websocket:
        print("WebSocket connected!")

        # Send 3 frames
        for i in range(3):
            websocket.send_bytes(img_bytes)
            data = websocket.receive_json()
            assert "results" in data
            print(f"Frame {i+1} results:", data)

    print("WebSocket test completed successfully!")

if __name__ == "__main__":
    test_websocket_detection()
