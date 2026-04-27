<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminPricingService;

final class AdminPricingController extends BaseController
{
    public function upsertPricingRule(): void
    {
        if (!$this->guardAdminPost()) return;

        $ruleCode = trim((string) ($_POST['rule_code'] ?? ''));
        $ruleValue = trim((string) ($_POST['rule_value'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? '')) ?: null;
        $isActive = !isset($_POST['is_active']) || (string) $_POST['is_active'] !== '0';

        if (
            $ruleCode === ''
            || $ruleValue === ''
            || preg_match('/^[A-Z0-9_.:-]{3,100}$/', $ruleCode) !== 1
            || mb_strlen($ruleValue) > 120
        ) {
            $this->adminError('rule_code inválido (A-Z0-9_.:-, 3-100) ou rule_value inválido.', 422, '/admin/pricing');
            return;
        }

        (new AdminPricingService())->upsertRule((int) ($_SESSION['auth_user_id'] ?? 0), $ruleCode, $ruleValue, $description, $isActive);
        $this->audit('admin.pricing_rule.saved', 'pricing_rule', null, ['rule_code' => $ruleCode]);
        $this->adminSuccess('Regra de pricing guardada.', '/admin/pricing', ['rule_code' => $ruleCode]);
    }

    public function upsertPricingExtra(): void
    {
        if (!$this->guardAdminPost()) return;

        $extraCode = trim((string) ($_POST['extra_code'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $amount = (float) ($_POST['amount'] ?? -1);
        $isActive = !isset($_POST['is_active']) || (string) $_POST['is_active'] !== '0';

        if (
            $extraCode === ''
            || $name === ''
            || preg_match('/^[A-Z0-9_.:-]{3,100}$/', $extraCode) !== 1
            || mb_strlen($name) < 3
            || mb_strlen($name) > 150
            || $amount < 0
            || $amount > 10000000
        ) {
            $this->adminError('extra_code/name inválido ou amount fora do intervalo permitido.', 422, '/admin/pricing');
            return;
        }

        (new AdminPricingService())->upsertExtra((int) ($_SESSION['auth_user_id'] ?? 0), $extraCode, $name, $amount, $isActive);
        $this->audit('admin.pricing_extra.saved', 'pricing_extra', null, ['extra_code' => $extraCode]);
        $this->adminSuccess('Extra de pricing guardado.', '/admin/pricing', ['extra_code' => $extraCode]);
    }
}
