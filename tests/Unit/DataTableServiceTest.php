<?php

use App\Modules\DataTable\Services\DataTableService;
use Illuminate\Http\Request;

it('builds a DataTableService instance from table name', function () {
    $instance = DataTableService::from('admin_users');
    expect($instance)->toBeInstanceOf(DataTableService::class);
});

it('applies searchable columns', function () {
    $instance = DataTableService::from('admin_users')
        ->searchable(['name', 'email']);
    expect($instance)->toBeInstanceOf(DataTableService::class);
});

it('applies orderable columns', function () {
    $instance = DataTableService::from('admin_users')
        ->orderable(['id', 'name']);
    expect($instance)->toBeInstanceOf(DataTableService::class);
});
