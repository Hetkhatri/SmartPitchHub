import sys
import json
import re

def to_float(val):
    if not val: return 0.0
    if isinstance(val, (int, float)): return float(val)
    try:
        # Remove currency symbols and commas
        clean = re.sub(r'[^\d.]', '', str(val))
        return float(clean)
    except:
        return 0.0

def extract_metrics(text):
    """
    Tries to extract revenue and growth from traction text
    Example: "2.5 Cr ARR, 85% Growth"
    """
    revenue = 0
    growth = 0
    
    # Extract Growth
    growth_match = re.search(r'(\d+(?:\.\d+)?)\s*%', text)
    if growth_match:
        growth = float(growth_match.group(1))
    
    # Extract Revenue (Lakhs/Cr/M/K)
    # Search for patterns like "2.5 Cr", "50 Lakhs", "1M"
    text = text.lower()
    
    cr_match = re.search(r'(\d+(?:\.\d+)?)\s*(?:cr|crore)', text)
    if cr_match:
        revenue = float(cr_match.group(1)) * 10000000
    else:
        lakh_match = re.search(r'(\d+(?:\.\d+)?)\s*lakh', text)
        if lakh_match:
            revenue = float(lakh_match.group(1)) * 100000
            
    return revenue, growth

def format_indian(amount):
    if amount >= 10000000:
        return f"₹{amount/10000000:.2f} Cr"
    elif amount >= 100000:
        return f"₹{amount/100000:.2f} Lakhs"
    return f"₹{amount:,.0f}"

def calculate_valuation_advice(data):
    if not data:
        return {"error": "Deep input data is empty"}

    industry = str(data.get('industry', 'other')).lower()
    stage = str(data.get('stage', 'pre-seed')).lower()
    traction_text = str(data.get('traction_text', ''))
    
    revenue = to_float(data.get('ttmRevenue', 0))
    growth = to_float(data.get('growthRate', 0))
    
    # If missing but traction text provided, extract
    if revenue <= 0 and traction_text:
        ext_rev, ext_growth = extract_metrics(traction_text)
        if ext_rev > 0: revenue = ext_rev
        if growth <= 0: growth = ext_growth
        
    valuation_ask = to_float(data.get('valuationAsk', 0))
    
    # 1. Base Multiplier by Industry
    multipliers = {
        'fintech': 12,
        'healthtech': 10,
        'edtech': 6,
        'saas': 15,
        'ecommerce': 4,
        'ai-ml': 20,
        'it-services': 8,
        'deeptech': 12,
        'logistics': 6,
        'other': 5
    }
    
    # Partial matching for industry (e.g. "AI & Machine Learning" matches "ai-ml")
    base_mult = 5
    for key, val in multipliers.items():
        if key in industry or industry in key:
            base_mult = val
            break
    
    # 2. Adjust for Growth
    mult_modifier = 1.0
    if growth > 100: mult_modifier = 1.5
    elif growth > 50: mult_modifier = 1.3
    elif growth > 20: mult_modifier = 1.1
    
    current_mult = base_mult * mult_modifier
    
    # 3. Adjust for Stage Risk
    stage_weights = {
        'pre-seed': 0.6,
        'seed': 0.8,
        'series-a': 1.0,
        'series-b': 1.2,
        'series-c': 1.5
    }
    weight = stage_weights.get(stage, 1.0)
    
    # 4. Estimated Valuation
    if revenue <= 0:
        est_val = {"pre-seed": 5000000, "seed": 20000000, "series-a": 80000000}.get(stage, 2500000)
    else:
        est_val = revenue * current_mult * weight
        
    # 5. Comparison
    diff_percent = ((valuation_ask - est_val) / est_val) * 100 if est_val > 0 else 0
    
    status = "Fair"
    if diff_percent > 40: status = "Aggressive"
    elif diff_percent < -20: status = "Conservative"
    
    # 6. Generate Message
    messages = [
        f"For a {industry.upper()} startup at {stage} stage, ",
        f"market multiples are approx {current_mult:.1f}x. "
    ]
    
    if revenue > 0:
        messages.append(f"With {format_indian(revenue)} revenue and {growth}% growth, ")
        messages.append(f"AI benchmark is {format_indian(est_val)}. ")
    else:
        messages.append(f"Benchmark for {stage} (limited revenue): {format_indian(est_val)}. ")
        
    if status == "Aggressive":
        messages.append("\n\nVerdict: Your ask is higher than average. Ensure your 'Valuation Justification' highlights team or tech moat.")
    elif status == "Conservative":
        messages.append("\n\nVerdict: Your valuation is attractive and may lead to oversubscription.")
    else:
        messages.append("\n\nVerdict: This is a realistic valuation range for current market conditions.")

    # 7. Confidence Score
    confidence = 50
    if revenue > 0: confidence += 20
    if growth > 0: confidence += 10
    if abs(diff_percent) < 50: confidence += 15
    confidence = min(confidence, 95)

    return {
        "benchmark_valuation": round(est_val, 2),
        "status": status,
        "advice": "".join(messages),
        "confidence": confidence
    }

if __name__ == "__main__":
    try:
        # Read from stdin (more robust for JSON in Windows/PHP pipes)
        input_str = sys.stdin.read()
        if not input_str:
            print(json.dumps({"error": "No input data provided"}))
            sys.exit(1)
            
        input_data = json.loads(input_str)
        result = calculate_valuation_advice(input_data)
        print(json.dumps(result))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
