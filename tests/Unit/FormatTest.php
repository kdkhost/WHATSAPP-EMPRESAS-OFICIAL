<?php

use App\Support\Format;

test('formata moeda brasileira', function () {
    expect(Format::money(1234.56))->toBe('R$ 1.234,56');
    expect(Format::money(0))->toBe('R$ 0,00');
    expect(Format::money(99.9))->toBe('R$ 99,90');
    expect(Format::money('97.90'))->toBe('R$ 97,90');
    expect(Format::money('150000.00'))->toBe('R$ 150.000,00');
    expect(Format::money('R$ 1.234,56'))->toBe('R$ 1.234,56');
});

test('normaliza moeda brasileira e decimal tecnico sem multiplicar valores', function () {
    expect(Format::decimal('97,90'))->toBe(97.9);
    expect(Format::decimal('R$ 97,90'))->toBe(97.9);
    expect(Format::decimal('97.90'))->toBe(97.9);
    expect(Format::decimal('9.790,00'))->toBe(9790.0);
    expect(Format::decimal('150000.00'))->toBe(150000.0);
});

test('formata CPF', function () {
    expect(Format::cpf('12345678901'))->toBe('123.456.789-01');
    expect(Format::cpf('123'))->toBe('123'); // incompleto retorna como esta
});

test('formata CNPJ', function () {
    expect(Format::cnpj('12345678000199'))->toBe('12.345.678/0001-99');
});

test('formata CEP', function () {
    expect(Format::cep('01001000'))->toBe('01001-000');
});

test('formata telefone celular', function () {
    expect(Format::phone('11987654321'))->toBe('(11) 98765-4321');
});

test('formata telefone fixo', function () {
    expect(Format::phone('1133334444'))->toBe('(11) 3333-4444');
});

test('whatsappNumber adiciona 55 se necessario', function () {
    expect(Format::whatsappNumber('11987654321'))->toBe('5511987654321');
    expect(Format::whatsappNumber('5511987654321'))->toBe('5511987654321');
});
