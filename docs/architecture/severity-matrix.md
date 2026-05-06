# Severity Matrix (missing vs too_short)

## Política única e previsível

Esta política harmoniza `DocumentComplianceValidationService` e `DocumentEditorialQualityGateService` para evitar contradições no fluxo de validação.

- **missing / empty (secções obrigatórias)** → `critical`.
  - Justificação: ausência estrutural impede avaliação académica mínima e deve bloquear entrega.
- **too_short (metodologia)**:
  - `< 90` palavras → `critical` (reforço automático não foi suficiente).
  - `90–119` palavras → `major` (há auto-reforço, mas requer validação final humana/sistémica).
  - `>= 120` palavras → sem issue de densidade metodológica.

## Efeito no fluxo

- O documento só fica `ok/is_compliant=true` quando não há issues/non-conformities `critical`.
- Issues `major` não bloqueiam sozinhas, mas sinalizam necessidade de revisão.
