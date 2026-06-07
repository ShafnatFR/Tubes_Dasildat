# scripts/predict.py
# Prediksi satu pasien dengan model pilihan (atau semua model sekaligus)
#
# Usage:
#   python predict.py <json_features> <model>
#
# <model> : svm | knn | dt | nn | all
#
# Contoh:
#   python predict.py "[6,148,72,35,0,33.6,0.627,50]" svm
#   python predict.py "[6,148,72,35,0,33.6,0.627,50]" all

import joblib
import pandas as pd
import sys
import json
import os
import warnings
warnings.filterwarnings('ignore')

# ── Path ──────────────────────────────────────────────────────────────────────
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MODEL_DIR = os.path.join(BASE_DIR, 'models')

MODEL_FILES = {
    'svm': os.path.join(MODEL_DIR, 'svm_model.sav'),
    'knn': os.path.join(MODEL_DIR, 'DigidawAlgoritma_K-NN.sav'),
    'dt':  os.path.join(MODEL_DIR, 'Tubes_Dasildat_DecisionTree.sav'),
    'nn':  os.path.join(MODEL_DIR, 'Tubes_Dasildat_NN.sav'),
}

SCALER_FILE = os.path.join(MODEL_DIR, 'Scaler.sav')

MODEL_LABELS = {
    'svm': 'SVM',
    'knn': 'K-NN',
    'dt':  'Decision Tree',
    'nn':  'Neural Network',
}

COLUMNS = [
    'Pregnancies', 'Glucose', 'BloodPressure',
    'SkinThickness', 'Insulin', 'BMI',
    'DiabetesPedigreeFunction', 'Age'
]

# ── Helper ────────────────────────────────────────────────────────────────────
def get_label(x):
    return 'Diabetes' if x == 1 else 'Tidak Diabetes'

def predict_single(model_key, df):
    scaler = joblib.load(SCALER_FILE)
    scaled = scaler.transform(df)
    model  = joblib.load(MODEL_FILES[model_key])
    result = model.predict(scaled)
    return get_label(result[0])

# ── Main ──────────────────────────────────────────────────────────────────────
if __name__ == '__main__':
    try:
        if len(sys.argv) < 3:
            print(json.dumps({'error': 'Usage: predict.py <json_features> <model>'}))
            sys.exit(1)

        raw_input  = sys.argv[1]
        model_key  = sys.argv[2].lower().strip()

        # Parse input
        input_data = json.loads(raw_input)
        df = pd.DataFrame([input_data], columns=COLUMNS)

        if model_key == 'all':
            # Prediksi semua model
            results = {}
            for key in MODEL_FILES:
                results[MODEL_LABELS[key]] = predict_single(key, df)
            print(json.dumps({'mode': 'all', 'results': results}))

        elif model_key in MODEL_FILES:
            label = predict_single(model_key, df)
            print(json.dumps({
                'mode':  'single',
                'model': MODEL_LABELS[model_key],
                'class': label
            }))

        else:
            print(json.dumps({'error': f'Model tidak dikenal: {model_key}. Pilih: svm, knn, dt, nn, all'}))
            sys.exit(1)

    except Exception as e:
        print(json.dumps({'error': str(e)}))
        sys.exit(1)
