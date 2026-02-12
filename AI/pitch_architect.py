# -*- coding: utf-8 -*-
import sys
import json
import random

def generate_draft(field, name, category):
    # Industry-specific keywords to make it feel "Expert"
    industry_keywords = {
        "fintech": ["payment gateway", "security", "frictionless", "blockchain", "compliance"],
        "healthtech": ["patient care", "diagnostics", "telemedicine", "HIPAA", "wellness"],
        "edtech": ["personalized learning", "curriculum", "upskilling", "mentorship", "engagement"],
        "saas": ["workflow automation", "scalability", "cloud-native", "integration", "enterprise-grade"],
        "ecommerce": ["direct-to-consumer", "logistics", "marketplace", "omnichannel", "retail"],
        "ai-ml": ["neural networks", "predictive analytics", "automation", "natural language", "datasets"]
    }
    
    keywords = industry_keywords.get(category.lower(), ["optimization", "efficiency", "innovation", "global", "platform"])
    kw1, kw2 = random.sample(keywords, 2)

    templates = {
        "shortPitch": [
            f"{name} is a revolutionary {category} platform providing {kw1} and {kw2} solutions for the modern era.",
            f"Transforming the {category} landscape with {name}: the first {kw1}-driven workspace for global businesses.",
            f"The future of {category} is {name}. We simplify {kw2} while maximizing output for our users."
        ],
        "problem": [
            f"In the current {category} sector, users face extreme {kw1} issues and rising costs. Current tools lack {kw2} integration.",
            f"The {category} industry is plagued by fragmentation. Businesses struggle to maintain {kw1} while scaling their {kw2} operations.",
            f"Lack of transparency and {kw2} in the {category} market is costing companies billions every year."
        ],
        "solution": [
            f"{name} leverages {kw1} technology to automate {category} workflows, delivering 3x faster {kw2} results.",
            f"Our platform offers a specialized approach to {category} by combining {kw1} with advanced {kw2} modules.",
            f"We provide a unified dashboard that makes {category} management simple, secure, and {kw2}-focused."
        ],
        "valueProposition": [
            f"Unlike existing tools, {name} offers a 99% reduction in {kw1} friction and enterprise-grade {kw2} support.",
            f"We are the only {category} provider offering a hybrid {kw1} model that scales with your growth.",
            f"Our proprietary {kw2} engine provides a 10x ROI for {category} enterprises compared to traditional methods."
        ],
        "targetMarket": [
            f"Mid-to-large scale enterprises in the {category} space looking to optimize {kw1}.",
            f"Innovative startups and individual professionals who require high-performance {kw2} tools.",
            f"Global {category} agencies seeking to automate their {kw1} and {kw2} pipelines."
        ],
        "revenueModel": [
            f"Tiered subscription model based on {kw1} volume and {kw2} requirements.",
            f"Usage-based pricing for {category} processing with an annual Enterprise licensing option.",
            f"A commission-based model taking a 5% fee on every {kw2} transaction secured by {name}."
        ],
        "traction": [
            f"Successfully processed over $500k in {kw2} transactions during our first quarter.",
            f"Partnered with leading {category} firms to benchmark our {kw1} performance.",
            f"Over 5,000 active users rely on {name} for their daily {kw2} operations."
        ]
    }
    
    drafts = templates.get(field, [f"A high-performance {category} solution for {name}."])
    return random.choice(drafts)

if __name__ == "__main__":
    try:
        # Read from stdin instead of argv
        input_data = sys.stdin.read()
        if not input_data:
            print(json.dumps({"success": False, "error": "No data received"}))
            sys.exit(1)
            
        data = json.loads(input_data)
        field = data.get("field")
        name = data.get("name", "Your Startup")
        category = data.get("category", "Technology") or "Technology"
        
        result = generate_draft(field, name, category)
        print(json.dumps({"success": True, "draft": result}))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))
