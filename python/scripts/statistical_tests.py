"""
Module Analyse exploratoire: hypothesis testing on top of the descriptive
EDA - a mean or a correlation coefficient alone doesn't say whether a
difference is real or just noise. Four tests cover the common analyst
questions: do two groups differ (t-test), do 3+ groups differ (ANOVA), are
two categorical variables independent (chi-square), and is a correlation
actually significant (Pearson).
"""

import sys
from pathlib import Path

import pandas as pd
from scipy import stats

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import read_table_cache  # noqa: E402
from common.json_utils import as_dict, run_script  # noqa: E402

SIGNIFICANCE_LEVEL = 0.05
MAX_ANOVA_GROUPS = 10


def main(input_data: dict) -> dict:
    df = read_table_cache(input_data["storage_path"])
    config = as_dict(input_data.get("config"))
    test_type = input_data["test_type"]

    handlers = {
        "t_test": _t_test,
        "chi_square": _chi_square,
        "anova": _anova,
        "correlation": _correlation,
    }

    if test_type not in handlers:
        raise ValueError(f"Unknown test type: {test_type}")

    return handlers[test_type](df, config)


def _significance_label(p_value: float) -> tuple[bool, str]:
    significant = p_value < SIGNIFICANCE_LEVEL
    label = (
        f"p = {p_value:.4f} < {SIGNIFICANCE_LEVEL} : la différence observée est statistiquement significative (peu probable due au hasard)."
        if significant
        else f"p = {p_value:.4f} ≥ {SIGNIFICANCE_LEVEL} : la différence observée n'est pas statistiquement significative (peut être due au hasard)."
    )
    return significant, label


def _t_test(df: pd.DataFrame, config: dict) -> dict:
    numeric_column, group_column = config["numeric_column"], config["group_column"]
    group_a, group_b = config["group_a"], config["group_b"]

    series = pd.to_numeric(df[numeric_column], errors="coerce")
    groups = df[group_column].astype(str)

    values_a = series[groups == group_a].dropna()
    values_b = series[groups == group_b].dropna()

    if values_a.empty or values_b.empty:
        raise ValueError("Un des deux groupes n'a aucune valeur numérique exploitable.")

    statistic, p_value = stats.ttest_ind(values_a, values_b, equal_var=False, nan_policy="omit")
    significant, label = _significance_label(float(p_value))

    return {
        "statistic": round(float(statistic), 4),
        "p_value": round(float(p_value), 6),
        "significant": significant,
        "interpretation": (
            f"Moyenne « {group_a} » = {values_a.mean():.2f} (n={len(values_a)}) vs "
            f"« {group_b} » = {values_b.mean():.2f} (n={len(values_b)}). {label}"
        ),
    }


def _anova(df: pd.DataFrame, config: dict) -> dict:
    numeric_column, group_column = config["numeric_column"], config["group_column"]

    series = pd.to_numeric(df[numeric_column], errors="coerce")
    grouped = df.assign(**{"__value__": series}).dropna(subset=["__value__", group_column])
    group_names = grouped[group_column].astype(str).unique().tolist()[:MAX_ANOVA_GROUPS]

    samples = [grouped.loc[grouped[group_column].astype(str) == name, "__value__"] for name in group_names]
    samples = [s for s in samples if len(s) > 1]

    if len(samples) < 2:
        raise ValueError("Il faut au moins 2 groupes avec des données pour une ANOVA.")

    statistic, p_value = stats.f_oneway(*samples)
    significant, label = _significance_label(float(p_value))

    means = ", ".join(f"{name}={sample.mean():.2f}" for name, sample in zip(group_names, samples))

    return {
        "statistic": round(float(statistic), 4),
        "p_value": round(float(p_value), 6),
        "significant": significant,
        "interpretation": f"Moyennes par groupe : {means}. {label}",
    }


def _chi_square(df: pd.DataFrame, config: dict) -> dict:
    column_a, column_b = config["column_a"], config["column_b"]

    contingency = pd.crosstab(df[column_a], df[column_b])

    if contingency.shape[0] < 2 or contingency.shape[1] < 2:
        raise ValueError("Il faut au moins 2 catégories dans chaque colonne pour un test du χ².")

    statistic, p_value, dof, _ = stats.chi2_contingency(contingency)
    significant, label = _significance_label(float(p_value))

    return {
        "statistic": round(float(statistic), 4),
        "p_value": round(float(p_value), 6),
        "significant": significant,
        "interpretation": (
            f"Tableau de contingence {contingency.shape[0]}×{contingency.shape[1]} (ddl={dof}). "
            + (
                f"{column_a} et {column_b} sont statistiquement dépendants (liés) : {label}"
                if significant
                else f"Rien n'indique un lien entre {column_a} et {column_b} : {label}"
            )
        ),
    }


def _correlation(df: pd.DataFrame, config: dict) -> dict:
    column_a, column_b = config["column_a"], config["column_b"]

    paired = df[[column_a, column_b]].apply(pd.to_numeric, errors="coerce").dropna()

    if len(paired) < 3:
        raise ValueError("Pas assez de paires de valeurs numériques pour calculer une corrélation.")

    statistic, p_value = stats.pearsonr(paired[column_a], paired[column_b])
    significant, label = _significance_label(float(p_value))

    strength = "forte" if abs(statistic) >= 0.7 else "modérée" if abs(statistic) >= 0.3 else "faible"
    direction = "positive" if statistic >= 0 else "négative"

    return {
        "statistic": round(float(statistic), 4),
        "p_value": round(float(p_value), 6),
        "significant": significant,
        "interpretation": f"Corrélation {direction} {strength} (r={statistic:.3f}, n={len(paired)}). {label}",
    }


if __name__ == "__main__":
    run_script(main)
