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
import time
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

def predict_single(model_key, df, scaler=None):
    if scaler is None:
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
            scaler = joblib.load(SCALER_FILE)
            results = {}
            timings = {}
            for key in MODEL_FILES:
                t0 = time.perf_counter()
                results[MODEL_LABELS[key]] = predict_single(key, df, scaler)
                timings[MODEL_LABELS[key]] = round((time.perf_counter() - t0) * 1000, 2)
            total_ms = round(sum(timings.values()), 2)
            print(json.dumps({
                'mode': 'all',
                'results': results,
                'timings': timings,
                'execution_time_ms': total_ms
            }))

        elif model_key in MODEL_FILES:
            t0 = time.perf_counter()
            label = predict_single(model_key, df)
            elapsed_ms = round((time.perf_counter() - t0) * 1000, 2)
            print(json.dumps({
                'mode':  'single',
                'model': MODEL_LABELS[model_key],
                'class': label,
                'execution_time_ms': elapsed_ms
            }))

        else:
            print(json.dumps({'error': f'Model tidak dikenal: {model_key}. Pilih: svm, knn, dt, nn, all'}))
            sys.exit(1)

    except Exception as e:
        print(json.dumps({'error': str(e)}))
        sys.exit(1)
