<?php

declare(strict_types=1);

namespace App\Controllers;

final class HomeController extends BaseController
{
    public function index(): void { $this->view('home/index'); }
    public function about(): void { $this->view('home/about'); }
    public function howItWorks(): void { $this->view('home/how-it-works'); }
    public function institutions(): void { $this->view('home/institutions'); }
    public function pricing(): void { $this->view('home/pricing'); }
    public function faq(): void { $this->view('home/faq'); }
    public function contact(): void { $this->view('home/contact'); }
}
