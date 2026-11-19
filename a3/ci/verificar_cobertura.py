#!/usr/bin/env python3
"""Quality gate de cobertura de um arquivo.

Uso: verificar_cobertura.py <clover.xml> <arquivo.php> <meta_em_percentual>

Le o relatorio Clover do PHPUnit, calcula a cobertura de linhas do arquivo
indicado e termina com erro (codigo 1) se ficar abaixo da meta.
"""
import sys
import xml.etree.ElementTree as ET


def main() -> int:
    if len(sys.argv) != 4:
        print("Uso: verificar_cobertura.py <clover.xml> <arquivo.php> <meta>")
        return 2

    caminho_xml, arquivo, meta_texto = sys.argv[1], sys.argv[2], sys.argv[3]
    meta = float(meta_texto.replace(",", "."))

    raiz = ET.parse(caminho_xml).getroot()

    for no in raiz.iter("file"):
        nome = no.get("name", "")
        if nome.replace("\\", "/").endswith("/" + arquivo):
            metricas = no.find("metrics")
            total = int(metricas.get("statements"))
            cobertas = int(metricas.get("coveredstatements"))
            percentual = 100.0 * cobertas / total if total else 0.0

            print(f"{arquivo}: {cobertas}/{total} linhas cobertas = {percentual:.2f}%")
            print(f"Meta do grupo: {meta:.2f}%")

            if percentual < meta:
                print("QUALITY GATE REPROVADO: cobertura abaixo da meta.")
                return 1

            print("Quality gate aprovado.")
            return 0

    print(f"ERRO: {arquivo} nao aparece no relatorio de cobertura.")
    return 1


if __name__ == "__main__":
    sys.exit(main())
