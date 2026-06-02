<?php

class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home/index');
    }

    public function profile(): void
    {
        AuthMiddleware::requireRole('Customer');
        $this->view('home/profile');
    }

    public function payment(): void
    {
        AuthMiddleware::requireRole('Customer');
        $this->view('home/payment');
    }

    public function schedule(): void
    {
        AuthMiddleware::requireRole('Customer');
        $this->view('home/schedule');
    }

    public function review(): void
    {
        AuthMiddleware::requireRole('Customer');
        $this->view('home/review');
    }

    public function staffHlv(): void
    {
        AuthMiddleware::requireChucVu('Personal Trainer');
        $this->view('staff/hlv');
    }

    public function staffReceptionist(): void
    {
        AuthMiddleware::requireChucVu('Receptionist');
        $this->view('staff/receptionist');
    }
}
