# scripts/predict_batch.py
# Prediksi batch dari file CSV untuk satu atau semua model
#
# Usage:
#   python predict_batch.py <path_csv_input> <model> <path_csv_output>
#
# <model> : svm | knn | dt | nn | all
#
# Contoh:
#   python predict_batch.py dataset/upload.csv svm dataset/hasil_svm.csv
#   python predict_batch.py dataset/upload.csv all dataset/hasil_semua.csv

import joblib
import pandas as pd
import sys
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

# ── Main ──────────────────────────────────────────────────────────────────────
if __name__ == '__main__':
    try:
        if len(sys.argv) < 4:
            print("ERROR: Usage: predict_batch.py <input_csv> <model> <output_csv>")
            sys.exit(1)

        input_csv  = sys.argv[1]
        model_key  = sys.argv[2].lower().strip()
        output_csv = sys.argv[3]

        # Baca CSV
        df_input = pd.read_csv(input_csv)

        # Validasi kolom — hanya ambil kolom yang diperlukan
        missing = [c for c in COLUMNS if c not in df_input.columns]
        if missing:
            print(f"ERROR: Kolom tidak ditemukan: {missing}")
            sys.exit(1)

        features = df_input[COLUMNS]

        # Scale input — wajib karena semua model dilatih dengan StandardScaler
        scaler   = joblib.load(SCALER_FILE)
        features_scaled = scaler.transform(features)

        if model_key == 'all':
            # Prediksi semua model — tambahkan kolom per model
            df_hasil = df_input.copy()
            for key, label in MODEL_LABELS.items():
                model    = joblib.load(MODEL_FILES[key])
                preds    = model.predict(features_scaled)
                col_name = f'Prediksi_{label.replace(" ", "_").replace("-", "_")}'
                df_hasil[col_name] = [get_label(p) for p in preds]

        elif model_key in MODEL_FILES:
            model  = joblib.load(MODEL_FILES[model_key])
            preds  = model.predict(features_scaled)
            df_hasil = df_input.copy()
            df_hasil['Prediksi'] = [get_label(p) for p in preds]

        else:
            print(f"ERROR: Model tidak dikenal: {model_key}")
            sys.exit(1)

        # Simpan hasil
        os.makedirs(os.path.dirname(output_csv), exist_ok=True)
        df_hasil.to_csv(output_csv, index=False)

        # Print summary ke stdout (dibaca PHP)
        total     = len(df_hasil)
        if model_key == 'all':
            # Hitung ringkasan per model
            summary_lines = [f"Total data: {total}"]
            for key, label in MODEL_LABELS.items():
                col = f'Prediksi_{label.replace(" ", "_").replace("-", "_")}'
                d_count = (df_hasil[col] == 'Diabetes').sum()
                n_count = total - d_count
                summary_lines.append(f"{label}: {d_count} Diabetes, {n_count} Tidak Diabetes")
            print("OK|" + "|".join(summary_lines))
        else:
            d_count = (df_hasil['Prediksi'] == 'Diabetes').sum()
            n_count = total - d_count
            label   = MODEL_LABELS[model_key]
            print(f"OK|Total data: {total}|{label}: {d_count} Diabetes, {n_count} Tidak Diabetes")

    except Exception as e:
        print(f"ERROR|{str(e)}")
        sys.exit(1)
