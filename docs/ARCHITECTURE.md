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
│   └── templates
└── views
```
