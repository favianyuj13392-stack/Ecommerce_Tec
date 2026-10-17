import cv2
import numpy as np
from detection import process_frame

def test_process_frame_empty_image():
    # Create dummy black image
    img = np.zeros((480, 640, 3), dtype=np.uint8)
    results = process_frame(img)
    assert isinstance(results, list)
    # Should not detect anything in black image
    assert len(results) == 0

if __name__ == "__main__":
    test_process_frame_empty_image()
    print("All tests passed!")
