<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PricingService;
use App\Services\RevisionService;

final class OrderController extends BaseController
{
    public function index(): void { $this->view('orders/index'); }
    public function create(): void { $this->view('orders/create'); }

    public function store(): void
    {
        $this->json(['message' => 'Pedido criado. Implementação via service/repository.']);
    }

    public function show(): void { $this->view('orders/show'); }

    public function pay(): void
    {
        $pricing = (new PricingService())->calculate([
            'order_id' => 1,
            'user_id' => 1,
            'work_type_id' => 1,
            'work_type_slug' => 'trabalho-pesquisa',
            'target_pages' => 15,
            'academic_level_multiplier' => 1.2,
            'complexity_multiplier' => 1.15,
            'urgency_multiplier' => 1.2,
            'extras' => ['needs_humanized_revision' => true],
            'requires_human_review' => false,
        ]);

        $this->json(['pricing' => $pricing->toArray()]);
    }

    public function requestRevision(): void
    {
        (new RevisionService())->request(1, 1, 'Ajustar fundamentação teórica.');
        $this->json(['message' => 'Pedido de revisão registado']);
    }
}
