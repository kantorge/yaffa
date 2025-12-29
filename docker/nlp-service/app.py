"""
YAFFA NLP Service - Payee Classification API

This service provides NLP-based classification for:
1. Finding duplicate payees using semantic similarity
2. Identifying transfer transactions based on payee names
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
from sentence_transformers import SentenceTransformer
from sklearn.metrics.pairwise import cosine_similarity
import numpy as np
import re
import logging

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}})

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Load the sentence transformer model (lightweight, ~80MB)
# This model converts text to embeddings for semantic similarity
logger.info("Loading sentence-transformers model...")
model = SentenceTransformer('all-MiniLM-L6-v2')
logger.info("Model loaded successfully")

# Transfer keywords and patterns
TRANSFER_PATTERNS = [
    # Direct transfer indicators
    r'\btransfer\b',
    r'\btfr\b',
    r'\bxfer\b',
    r'\bmove money\b',
    
    # Account-to-account patterns
    r'\bto\s+\w+\s+account\b',
    r'\bfrom\s+\w+\s+account\b',
    r'\baccount\s+transfer\b',
    
    # Common bank transfer terms
    r'\binternal transfer\b',
    r'\bown account\b',
    r'\bwithdrawal.*account\b',
    r'\bdeposit.*account\b',
    
    # Payment method indicators
    r'\bfaster payment\b',
    r'\bbacs\b',
    r'\bstanding order\b',
    r'\bdirect debit\s+from\s+own\b',
]

def normalize_payee_name(name):
    """Normalize payee name for better matching"""
    if not name:
        return ""
    
    # Convert to lowercase
    name = name.lower().strip()
    
    # Remove common suffixes
    suffixes = ['ltd', 'limited', 'inc', 'llc', 'plc', 'corp', 'corporation']
    for suffix in suffixes:
        name = re.sub(r'\b' + suffix + r'\b', '', name)
    
    # Remove special characters but keep spaces
    name = re.sub(r'[^\w\s]', ' ', name)
    
    # Collapse multiple spaces
    name = re.sub(r'\s+', ' ', name).strip()
    
    return name


@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'model': 'all-MiniLM-L6-v2',
        'version': '1.0.0'
    })


@app.route('/api/classify/transfer', methods=['POST'])
def classify_transfer():
    """
    Classify if a payee name indicates a transfer transaction
    
    Request body:
    {
        "payee_name": "Transfer to Savings Account",
        "transaction_type": "withdrawal",  # optional context
        "description": "..."  # optional additional context
    }
    
    Response:
    {
        "is_transfer": true,
        "confidence": 0.95,
        "matched_patterns": ["transfer", "to.*account"],
        "recommendation": "Convert to transfer transaction"
    }
    """
    try:
        data = request.get_json()
        
        if not data or 'payee_name' not in data:
            return jsonify({'error': 'payee_name is required'}), 400
        
        payee_name = data.get('payee_name', '')
        description = data.get('description', '')
        transaction_type = data.get('transaction_type', '')
        
        # Combine text for analysis
        text_to_analyze = f"{payee_name} {description}".lower()
        
        # Check against transfer patterns
        matched_patterns = []
        for pattern in TRANSFER_PATTERNS:
            if re.search(pattern, text_to_analyze, re.IGNORECASE):
                matched_patterns.append(pattern)
        
        # Calculate confidence based on matches
        confidence = min(len(matched_patterns) * 0.3, 1.0)
        is_transfer = len(matched_patterns) > 0
        
        # Additional heuristics
        if 'account' in text_to_analyze and any(word in text_to_analyze for word in ['to', 'from', 'transfer']):
            confidence = max(confidence, 0.8)
            is_transfer = True
        
        recommendation = None
        if is_transfer and transaction_type in ['withdrawal', 'deposit']:
            recommendation = f"Convert {transaction_type} to transfer transaction"
        
        return jsonify({
            'is_transfer': is_transfer,
            'confidence': round(confidence, 3),
            'matched_patterns': matched_patterns,
            'recommendation': recommendation
        })
        
    except Exception as e:
        logger.error(f"Error in classify_transfer: {str(e)}")
        return jsonify({'error': str(e)}), 500


@app.route('/api/find-duplicates', methods=['POST'])
def find_duplicates():
    """
    Find duplicate payees using semantic similarity
    
    Request body:
    {
        "payees": [
            {"id": 1, "name": "Amazon.com"},
            {"id": 2, "name": "Amazon UK"},
            {"id": 3, "name": "Tesco PLC"},
            {"id": 4, "name": "TESCO Limited"}
        ],
        "threshold": 0.85  # optional, default 0.85
    }
    
    Response:
    {
        "duplicate_groups": [
            {
                "primary": {"id": 1, "name": "Amazon.com"},
                "duplicates": [
                    {"id": 2, "name": "Amazon UK", "similarity": 0.92}
                ],
                "recommendation": "Merge payee 2 into payee 1"
            },
            {
                "primary": {"id": 3, "name": "Tesco PLC"},
                "duplicates": [
                    {"id": 4, "name": "TESCO Limited", "similarity": 0.96}
                ],
                "recommendation": "Merge payee 4 into payee 3"
            }
        ],
        "total_duplicates_found": 2
    }
    """
    try:
        data = request.get_json()
        
        if not data or 'payees' not in data:
            return jsonify({'error': 'payees array is required'}), 400
        
        payees = data.get('payees', [])
        threshold = data.get('threshold', 0.85)
        
        if len(payees) < 2:
            return jsonify({
                'duplicate_groups': [],
                'total_duplicates_found': 0
            })
        
        # Normalize payee names
        normalized_names = [normalize_payee_name(p.get('name', '')) for p in payees]
        
        # Generate embeddings for all payee names
        logger.info(f"Generating embeddings for {len(payees)} payees...")
        embeddings = model.encode(normalized_names)
        
        # Calculate cosine similarity matrix
        similarity_matrix = cosine_similarity(embeddings)
        
        # Find duplicate groups
        processed = set()
        duplicate_groups = []
        
        for i in range(len(payees)):
            if i in processed:
                continue
            
            # Find similar payees
            duplicates = []
            for j in range(i + 1, len(payees)):
                if j in processed:
                    continue
                
                similarity = similarity_matrix[i][j]
                
                if similarity >= threshold:
                    duplicates.append({
                        'id': payees[j].get('id'),
                        'name': payees[j].get('name'),
                        'similarity': round(float(similarity), 3)
                    })
                    processed.add(j)
            
            if duplicates:
                # Sort by similarity (highest first)
                duplicates.sort(key=lambda x: x['similarity'], reverse=True)
                
                duplicate_groups.append({
                    'primary': {
                        'id': payees[i].get('id'),
                        'name': payees[i].get('name')
                    },
                    'duplicates': duplicates,
                    'recommendation': f"Merge {len(duplicates)} payee(s) into payee {payees[i].get('id')}"
                })
                
                processed.add(i)
        
        logger.info(f"Found {len(duplicate_groups)} duplicate groups")
        
        return jsonify({
            'duplicate_groups': duplicate_groups,
            'total_duplicates_found': len(duplicate_groups)
        })
        
    except Exception as e:
        logger.error(f"Error in find_duplicates: {str(e)}")
        return jsonify({'error': str(e)}), 500


@app.route('/api/similarity', methods=['POST'])
def calculate_similarity():
    """
    Calculate similarity between two payee names
    
    Request body:
    {
        "name1": "Amazon.com",
        "name2": "Amazon UK"
    }
    
    Response:
    {
        "similarity": 0.92,
        "are_similar": true,
        "threshold": 0.85
    }
    """
    try:
        data = request.get_json()
        
        if not data or 'name1' not in data or 'name2' not in data:
            return jsonify({'error': 'name1 and name2 are required'}), 400
        
        name1 = normalize_payee_name(data.get('name1', ''))
        name2 = normalize_payee_name(data.get('name2', ''))
        threshold = data.get('threshold', 0.85)
        
        # Generate embeddings
        embeddings = model.encode([name1, name2])
        
        # Calculate similarity
        similarity = cosine_similarity([embeddings[0]], [embeddings[1]])[0][0]
        
        return jsonify({
            'similarity': round(float(similarity), 3),
            'are_similar': similarity >= threshold,
            'threshold': threshold
        })
        
    except Exception as e:
        logger.error(f"Error in calculate_similarity: {str(e)}")
        return jsonify({'error': str(e)}), 500


if __name__ == '__main__':
    # For production, use gunicorn instead
    app.run(host='0.0.0.0', port=8083, debug=False)
