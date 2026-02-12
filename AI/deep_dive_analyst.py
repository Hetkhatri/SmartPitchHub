# -*- coding: utf-8 -*-
import sys
import json
import random

def to_float(val):
    if not val: return 0.0
    try:
        if isinstance(val, str):
            # Handle Indian formatting with commas if any
            val = val.replace("₹", "").replace(",", "")
        return float(val)
    except:
        return 0.0

def analyze_startup(pitch):
    name = pitch.get('startup_name', 'Startup')
    industry = pitch.get('industry', 'Technology')
    stage = pitch.get('stage', 'Seed')
    
    # Calculate valuation using share_price * shares_issued if possible
    shares_issued = to_float(pitch.get('shares_issued', 0))
    share_price = to_float(pitch.get('share_price', 0))
    
    if shares_issued > 0 and share_price > 0:
        valuation = shares_issued * share_price
    elif pitch.get('share_price'):
        valuation = to_float(pitch.get('share_price')) * 50000 # fallback placeholder
    else:
        valuation = to_float(pitch.get('valuation', 0))

    problem = pitch.get('problem', '')
    solution = pitch.get('solution', '')
    
    # Handle calculated raised amount or raw column
    amount_raised = to_float(pitch.get('amount_raised', pitch.get('raised_amount', 0)))
    funding_goal = to_float(pitch.get('funding_goal', 1))
    if funding_goal <= 0: funding_goal = 1

    # 1. Risk Score Logic
    risk_map = {
        'Idea': 85, 'MVP': 65, 'Revenue': 45, 'Scaling': 25
    }
    base_risk = risk_map.get(stage, 60)
    
    # Adjust for funding progress
    progress = (amount_raised / funding_goal) * 100
    if progress > 75: base_risk -= 10
    elif progress < 10: base_risk += 5
    
    # Adjust for Industry Volatility
    # Final Risk Score calculation (Removed randomness for consistency)
    risk_score = min(98, max(5, base_risk))
    
    # 2. Sentiment Analysis (Mocked)
    sentiment = "Positive" if risk_score < 50 else "Neutral" if risk_score < 75 else "Cautious"
    
    # 3. AI Generated Insights
    insights = [
        f"Directly addresses the problem of '{problem[:40]}...' in the {industry} sector.",
        f"Valuation of ₹{valuation:,.0f} reflects a {stage}-appropriate multiple.",
        f"Recent fundraising momentum ({progress:.1f}%) suggests strong investor sentiment."
    ]

    # 4. Competitive Landscape
    swot = {
        "Strengths": ["Proprietary MVP", "Zero debt structure", "Low CAC potential"],
        "Weaknesses": ["High dependency on founder", "Niche target market", "Scaling costs"],
        "Opportunities": ["Post-funding marketing blitz", "API-first distribution", "Cross-sector licensing"],
        "Threats": ["Competitor price wars", "Regulatory shifts", "Talent retention"]
    }

    return {
        "success": True,
        "risk_score": risk_score,
        "sentiment": sentiment,
        "summary": f"Our AI model labels {name} as a '{sentiment}' prospect. Their solution has the potential to disrupt {industry} by simplifying complex {problem[:30]} workflows.",
        "insights": insights,
        "swot": swot,
        "recommendation": "Strong Buy" if risk_score < 40 else "Accumulate" if risk_score < 60 else "Hold" if risk_score < 80 else "Speculative"
    }

if __name__ == "__main__":
    try:
        # Read from stdin and strip any potential whitespace/BOM
        raw_input = sys.stdin.read().strip()
        
        if not raw_input:
            print(json.dumps({"success": False, "error": "AI Engine received no data."}))
            sys.exit(1)
            
        # Ensure we only try to parse the JSON part if there's any garbage
        start_idx = raw_input.find('{')
        end_idx = raw_input.rfind('}')
        if start_idx != -1 and end_idx != -1:
            json_str = raw_input[start_idx:end_idx+1]
            data = json.loads(json_str)
            result = analyze_startup(data)
            print(json.dumps(result))
        else:
            print(json.dumps({"success": False, "error": "Invalid data format received."}))
            
    except Exception as e:
        # Return a clean JSON error
        print(json.dumps({"success": False, "error": f"Internal AI Error: {str(e)}"}))
