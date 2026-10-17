import os
import cv2
import numpy as np
import tensorflow as tf
from tracker import CentroidTracker

# Config
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, "models/ssd_mobilenet.tflite")
LABELS_PATH = os.path.join(BASE_DIR, "models/ssd_mobilenet.txt")

class DetectionService:
    def __init__(self):
        # Load labels
        with open(LABELS_PATH, 'r') as f:
            self.labels = [line.strip() for line in f.readlines()]

        # Load TFLite model
        self.interpreter = tf.lite.Interpreter(model_path=MODEL_PATH)
        self.interpreter.allocate_tensors()

        self.input_details = self.interpreter.get_input_details()
        self.output_details = self.interpreter.get_output_details()

        self.input_shape = self.input_details[0]['shape']
        self.input_height = self.input_shape[1]
        self.input_width = self.input_shape[2]

        self.tracker = CentroidTracker()

        # Define person label index. In COCO (often used with MobileNet SSD), person is typically 0.
        # But we check the labels file. Usually, it's 'person'.
        self.person_label = 'person'

    def _preprocess(self, image):
        img_resized = cv2.resize(image, (self.input_width, self.input_height))
        input_type = self.input_details[0]['dtype']
        if input_type == np.float32:
            input_data = (np.float32(img_resized) - 127.5) / 127.5
        else:
            input_data = np.uint8(img_resized)
        input_data = np.expand_dims(input_data, axis=0)
        return input_data

    def process_frame(self, image):
        height, width, _ = image.shape
        input_data = self._preprocess(image)

        self.interpreter.set_tensor(self.input_details[0]['index'], input_data)
        self.interpreter.invoke()

        # Output indices vary by model, usually:
        # 0: boxes, 1: classes, 2: scores, 3: num_detections
        boxes = self.interpreter.get_tensor(self.output_details[0]['index'])[0] # Bounding box coordinates [ymin, xmin, ymax, xmax]
        classes = self.interpreter.get_tensor(self.output_details[1]['index'])[0] # Class index
        scores = self.interpreter.get_tensor(self.output_details[2]['index'])[0] # Confidence

        rects = []
        for i in range(len(scores)):
            if scores[i] > 0.5:
                class_id = int(classes[i])
                if class_id < len(self.labels) and self.labels[class_id] == self.person_label:
                    ymin, xmin, ymax, xmax = boxes[i]
                    start_x = int(xmin * width)
                    start_y = int(ymin * height)
                    end_x = int(xmax * width)
                    end_y = int(ymax * height)
                    rects.append([start_x, start_y, end_x, end_y])

        # Update tracker
        objects = self.tracker.update(rects)

        # We calculate proximity using the bounding box area.
        # A threshold area relative to the image size can determine if a person is "close".
        total_area = width * height
        proximity_threshold_ratio = 0.15 # 15% of the screen implies they are close

        results = []
        for (objectID, centroid) in objects.items():
            is_close = False
            rect = self.tracker.rects.get(objectID)
            area_ratio = 0.0
            if rect is not None:
                startX, startY, endX, endY = rect
                box_width = endX - startX
                box_height = endY - startY
                box_area = box_width * box_height
                area_ratio = box_area / total_area
                if area_ratio >= proximity_threshold_ratio:
                    is_close = True

            results.append({
                "id": objectID,
                "centroid": centroid.tolist() if isinstance(centroid, np.ndarray) else centroid,
                "is_close": is_close,
                "proximity_ratio": round(area_ratio, 4)
            })

        return results

# Singleton instance
service = DetectionService()

def process_frame(image):
    return service.process_frame(image)
