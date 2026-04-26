<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminPricingService;

final class AdminPricingController extends AdminActionController
{
    public function upsertPricingRule(): void
    {
        if (!$this->guardAdminPost()) return;

        $ruleCode = trim((string) ($_POST['rule_code'] ?? ''));
        $ruleValue = trim((string) ($_POST['rule_value'] ?? ''));
        if ($ruleCode === '' || $ruleValue === '') {
            $this->adminError('rule_code e rule_value são obrigatórios.', 422, '/admin/pricing');
            return;
        }

        (new AdminPricingService())->upsertRule((int) ($_SESSION['auth_user_id'] ?? 0), $ruleCode, $ruleValue, trim((string) ($_POST['description'] ?? '')) ?: null, !isset($_POST['is_active']) || (string) $_POST['is_active'] !== '0');
        $this->audit('admin.pricing_rule.saved', 'pricing_rule', null, ['rule_code' => $ruleCode]);
        $this->adminSuccess('Regra de pricing guardada.', '/admin/pricing', ['rule_code' => $ruleCode]);
    }

    public function upsertPricingExtra(): void
    {
        if (!$this->guardAdminPost()) return;

        $extraCode = trim((string) ($_POST['extra_code'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $amount = (float) ($_POST['amount'] ?? -1);
        if ($extraCode === '' || $name === '' || $amount < 0) {
            $this->adminError('extra_code, name e amount válido são obrigatórios.', 422, '/admin/pricing');
            return;
        }

        (new AdminPricingService())->upsertExtra((int) ($_SESSION['auth_user_id'] ?? 0), $extraCode, $name, $amount, !isset($_POST['is_active']) || (string) $_POST['is_active'] !== '0');
        $this->audit('admin.pricing_extra.saved', 'pricing_extra', null, ['extra_code' => $extraCode]);
        $this->adminSuccess('Extra de pricing guardado.', '/admin/pricing', ['extra_code' => $extraCode]);
    }
}
