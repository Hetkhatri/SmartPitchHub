import sys
import pytesseract
from PIL import Image
import json
import os
import re

# Connect to OCR Engine
pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

def analyze_image(image_path):
    response = {"status": "error", "doc_score": 0, "fraud_risk": "High", "ocr_text": ""}
    try:
        if not os.path.exists(image_path):
            response["message"] = "File not found"
            return response # Return dict, not json string yet

        # Read Text
        text = pytesseract.image_to_string(Image.open(image_path))
        clean_text = text.upper().replace('\n', ' ')
        
        # Scoring Logic
        keywords = ["INCOME", "TAX", "DEPARTMENT", "GOVT", "INDIA", "PERMANENT", "ACCOUNT", "MALE", "FEMALE", "DOB", "DATE"]
        matches = 0
        for word in keywords:
            if word in clean_text:
                matches += 1
        
        # INCREASED SCORING: 20 points per match to be nicer
        doc_score = 40 + (matches * 20)
        if doc_score > 98: doc_score = 98.5
        
        risk = "Low"
        if matches < 1:
            risk = "High"
            doc_score = 45.0
        elif matches < 3:
            risk = "Medium"

        response["status"] = "success"
        response["doc_score"] = doc_score
        response["doc_risk"] = risk # Internal risk for doc only
        response["ocr_text"] = clean_text[:100]
        
    except Exception as e:
        response["message"] = str(e)

    return response

def detect_fraud(data_json):
    # This function analyzes the USER DATA for patterns
    data = json.loads(data_json)
    email = data.get('email', '').lower()
    phone = data.get('phone', '')
    ip = data.get('ip', '127.0.0.1')
    
    fraud_score = 0
    risk_reasons = []

    # 1. EMAIL PATTERN CHECK
    # Check for temporary emails or aliases (e.g., name+1@gmail.com)
    if "+" in email:
        fraud_score += 30
        risk_reasons.append("Email Alias Detected")
    
    # Check for suspicious domains
    suspicious_domains = ["tempmail.com", "10minutemail.com", "fake.com"]
    if any(domain in email for domain in suspicious_domains):
        fraud_score += 80
        risk_reasons.append("Disposable Email Domain")

    # 2. PHONE PATTERN CHECK
    # Check if phone is all same digits (e.g., 9999999999)
    if len(set(phone)) == 1:
        fraud_score += 90
        risk_reasons.append("Invalid Phone Pattern")
    
    # 3. IP ADDRESS CHECK
    # Check if IP is local (safe) or looks like a proxy (mock logic)
    if ip == "127.0.0.1" or ip == "::1":
        fraud_score += 0 # Localhost is safe for dev
    else:
        # Real AI would check a GeoIP database here
        pass

    # Calculate Final Risk Level
    risk_level = "Low"
    if fraud_score > 70: risk_level = "High"
    elif fraud_score > 30: risk_level = "Medium"

    return {
        "fraud_score": fraud_score,
        "risk_level": risk_level,
        "reasons": risk_reasons
    }

# MAIN EXECUTION CONTROLLER
if __name__ == "__main__":
    mode = sys.argv[1] # "ocr" or "fraud"
    
    if mode == "ocr":
        image_path = sys.argv[2]
        print(json.dumps(analyze_image(image_path)))
        
    elif mode == "fraud":
        # Pass user data as a JSON string
        user_data = sys.argv[2]
        print(json.dumps(detect_fraud(user_data)))