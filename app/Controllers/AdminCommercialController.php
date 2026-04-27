<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CouponRepository;
use App\Services\AdminCommercialService;

final class AdminCommercialController extends BaseController
{
    public function createCoupon(): void
    {
        if (!$this->guardAdminPost()) return;

        $service = new AdminCommercialService();
        $payload = $service->couponPayloadFromRequest($_POST);
        if ($payload === null) {
            $this->adminError('Dados inválidos para criar cupão.', 422, '/admin/coupons');
            return;
        }

        $id = $service->createCoupon($payload);
        if ($id === null) {
            $this->adminError('Já existe um cupão activo com esse código.', 409, '/admin/coupons');
            return;
        }

        $this->audit('admin.coupon.created', 'coupon', $id, ['code' => $payload['code']]);
        $this->adminSuccess('Cupão criado com sucesso.', '/admin/coupons', ['coupon_id' => $id]);
    }

    public function updateCoupon(int $id): void
    {
        if (!$this->guardAdminPost()) return;

        $service = new AdminCommercialService();
        $payload = $service->couponPayloadFromRequest($_POST);
        if ($payload === null) {
            $this->adminError('Dados inválidos para actualizar cupão.', 422, '/admin/coupons');
            return;
        }

        if (!$service->updateCoupon($id, $payload)) {
            $this->adminError('Cupão não encontrado.', 404, '/admin/coupons');
            return;
        }

        $this->audit('admin.coupon.updated', 'coupon', $id, ['code' => $payload['code']]);
        $this->adminSuccess('Cupão actualizado.', '/admin/coupons', ['coupon_id' => $id]);
    }

    public function toggleCoupon(int $id): void
    {
        if (!$this->guardAdminPost()) return;

        $active = !empty($_POST['is_active']);
        $updated = (new CouponRepository())->setActive($id, $active);
        if (!$updated) {
            $this->adminError('Cupão não encontrado para alteração de estado.', 404, '/admin/coupons');
            return;
        }

        $this->audit('admin.coupon.toggled', 'coupon', $id, ['is_active' => $active]);
        $this->adminSuccess($active ? 'Cupão activado.' : 'Cupão inactivado.', '/admin/coupons', ['coupon_id' => $id, 'is_active' => $active]);
    }

    public function createDiscount(): void
    {
        if (!$this->guardAdminPost()) return;

        $service = new AdminCommercialService();
        $id = $service->createDiscount($_POST, (int) ($_SESSION['auth_user_id'] ?? 1));
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($id === null) {
            $this->adminError('Dados inválidos para criar desconto.', 422, '/admin/discounts');
            return;
        }

        $this->audit('admin.discount.created', 'user_discount', $id, ['user_id' => $userId]);
        $this->adminSuccess('Desconto criado.', '/admin/discounts', ['discount_id' => $id]);
    }

    public function updateDiscount(int $id): void
    {
        if (!$this->guardAdminPost()) return;

        if (!(new AdminCommercialService())->updateDiscount($id, $_POST)) {
            $this->adminError('Dados inválidos para atualizar desconto.', 422, '/admin/discounts');
            return;
        }
        $this->audit('admin.discount.updated', 'user_discount', $id);
        $this->adminSuccess('Desconto atualizado.', '/admin/discounts', ['discount_id' => $id]);
    }
}
