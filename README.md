# MOZacad

Plataforma de gestão de pedidos académicos com pagamento, acompanhamento, revisão humana e entrega de documentos.

## Fluxo de entrega manual

1. O cliente cria o pedido e efectua o pagamento.
2. Quando o pagamento é confirmado, o pedido passa para `awaiting_manual_upload`.
3. A equipa prepara o trabalho fora da aplicação e carrega o ficheiro DOCX ou PDF em **Administração → Pedidos**.
4. O documento carregado é encaminhado para a fila de revisão humana e, depois de aprovado, fica disponível para download pelo cliente.

Não há geração automática de documentos, fornecedores de IA, jobs de IA ou requisitos de chaves de IA.

## Desenvolvimento

```bash
composer install
composer db:setup
php -S localhost:8000 -t public
```
