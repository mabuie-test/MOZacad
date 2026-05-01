# Auditoria técnica — último commit do PR “Add priority and specificity tie-breaks to dynamic academic profile selection”

Commit auditado: `c652a4f`.

## Resultado
- Ordenação determinística implementada com `priority DESC`, `specificity_score DESC`, `id ASC`.
- Telemetria contém os campos solicitados e é preenchida com dados de runtime.
- Há gap na regra de `specificityScore`: não há enforcement explícito de “mínimo 1 por variante” para entradas malformadas; nesses casos a variante pode contribuir `0`.
- Configuração atual possui `priority` explícita e inteira em todos os perfis existentes no arquivo.
