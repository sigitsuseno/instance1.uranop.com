<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$data = [
  'effective_date' => '2026-06-15',
  'jht_employer' => 3.70,
  'jht_employee' => 2.00,
  'jkk' => 0.24,
  'jkm' => 0.30,
  'jp_employer' => 2.00,
  'jp_employee' => 1.00,
  'kesehatan_employer' => 4.00,
  'kesehatan_employee' => 1.00,
  'max_wage_cap' => 12000000,
  'description' => '',
];

$rules = [
    'effective_date'       => 'required|date',
    'is_active'            => 'boolean',
    'jht_employer'         => 'numeric|min:0|max:100',
    'jht_employee'         => 'numeric|min:0|max:100',
    'jkk'                  => 'numeric|min:0|max:100',
    'jkm'                  => 'numeric|min:0|max:100',
    'jp_employer'          => 'numeric|min:0|max:100',
    'jp_employee'          => 'numeric|min:0|max:100',
    'kesehatan_employer'   => 'numeric|min:0|max:100',
    'kesehatan_employee'   => 'numeric|min:0|max:100',
    'max_wage_cap'         => 'nullable|numeric|min:0',
    'description'          => 'nullable|string|max:500',
];

$validator = \Illuminate\Support\Facades\Validator::make($data, $rules);

if ($validator->fails()) {
    echo "Validation failed!\n";
    print_r($validator->errors()->toArray());
} else {
    echo "Validation passed!\n";
    $validatedData = $validator->validated();
    print_r($validatedData);
}
