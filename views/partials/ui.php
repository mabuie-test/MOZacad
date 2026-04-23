<?php

$statusMap = static function (string $status): array {
    return match ($status) {
        'draft' => ['Rascunho', 'secondary', 'Pedido iniciado e ainda não submetido.'],
        'pending_payment', 'pending', 'issued' => ['Pendente', 'warning', 'Aguardando pagamento ou confirmação inicial.'],
        'processing', 'queued', 'pending_confirmation' => ['Em processamento', 'info', 'Equipa e motor estão a processar este passo.'],
        'under_human_review', 'pending_human_review', 'assigned' => ['Revisão humana', 'primary', 'Documento sob validação especializada.'],
        'ready', 'paid', 'approved' => ['Concluído', 'success', 'Etapa concluída com sucesso.'],
        'revision_requested', 'returned_for_revision' => ['Revisão solicitada', 'warning', 'Existe uma iteração de revisão activa.'],
        'failed', 'rejected', 'cancelled', 'expired' => ['Falhou', 'danger', 'Fluxo interrompido, requer intervenção.'],
        default => [ucfirst(str_replace('_', ' ', $status)), 'secondary', 'Estado registado no sistema.'],
    };
};

$formatMoney = static fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . ' MZN';

$badge = static function (string $status) use ($statusMap): string {
    [$label, $tone] = $statusMap($status);
    return '<span class="badge soft-badge text-bg-' . $tone . '">' . htmlspecialchars($label) . '</span>';
};

$statusHint = static function (string $status) use ($statusMap): string {
    [, , $hint] = $statusMap($status);
    return $hint;
};

$emptyState = static function (string $title, string $body, ?string $ctaHref = null, ?string $ctaLabel = null): string {
    $cta = '';
    if ($ctaHref !== null && $ctaLabel !== null) {
        $cta = '<a class="btn btn-sm btn-primary mt-3" href="' . htmlspecialchars($ctaHref) . '">' . htmlspecialchars($ctaLabel) . '</a>';
    }

    return '<div class="empty-state"><h3 class="h6 mb-1">' . htmlspecialchars($title) . '</h3><p class="mb-0 text-secondary">' . htmlspecialchars($body) . '</p>' . $cta . '</div>';
};
