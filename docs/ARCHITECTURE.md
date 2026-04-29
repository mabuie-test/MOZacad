# Arquitectura Moz Acad

## Camadas
- **Presentation:** Controllers + Views + rotas.
- **Application:** Services e Jobs.
- **Domain:** DTOs e regras de negócio.
- **Infrastructure:** Repositories, DB, integração Débito, filesystem DOCX.

## Fluxos críticos
1. Criação de pedido em wizard (5 etapas)
2. Cálculo de preço + desconto de utilizador
3. Geração de factura + init pagamento M-Pesa
4. Polling de estado (principal) + webhook (complementar)
5. Pipeline de geração, humanização pt_MZ, formatação institucional, DOCX
6. Encaminhamento para revisão humana quando obrigatório (monografia)

## Árvore de ficheiros
```text
.
├── app
│   ├── Controllers
│   ├── DTOs
│   ├── Helpers
│   ├── Jobs
│   ├── Middleware
│   ├── Models
│   ├── Repositories
│   └── Services
├── bootstrap
├── config
├── database
│   ├── migrations
│   └── seeders
├── docs
├── public
├── routes
├── scripts
├── storage
│   ├── generated
│   ├── logs
│   └── templates (candidatos; montagem actual continua programática)
└── views
```
## Normas institucionais (fallback PDF/OCR)

- O pipeline tenta carregar por ordem: `norma.txt` → `metadata.normalized_text` → extração de `norma.pdf` com `pdftotext`.
- Se `pdftotext` falhar, tenta OCR local com `ocrmypdf` e nova extração para texto.
- Em ambientes `public_html` sem utilitários nativos, usa OCR remoto via `NORM_OCR_PIPELINE_ENDPOINT` (upload do PDF, polling com timeout/retry, download de texto).
- Quando OCR é bem-sucedido, persiste texto normalizado em `norma.txt` e `metadata.normalized_text` para evitar retrabalho.
- Se todas as estratégias falharem, o contexto retorna `source=pdf_unparsed`; a activação administrativa da norma é bloqueada com mensagem acionável para produção.


## Convergência fresh vs upgrade
- `database/setup.php` aplica schema base/migrations e executa `SchemaConvergenceService` para garantir equivalência estrutural efectiva.
- `scripts/validate_runtime.php` faz smoke checks de fluxos críticos e inconsistências operacionais.
