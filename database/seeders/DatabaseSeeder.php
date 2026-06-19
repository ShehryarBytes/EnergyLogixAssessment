<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Formula;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedContracts();
        $this->seedFormulas();
    }

    private function seedUsers(): void
    {
        User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@energylogix.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        User::create([
            'name'     => 'Viewer User',
            'email'    => 'viewer@energylogix.com',
            'password' => Hash::make('password'),
            'role'     => 'viewer',
        ]);
    }

    private function seedContracts(): void
    {
        $contracts = [
            ['customer_name' => 'Northgate Industrial Ltd',       'annual_usage' => '284000.0000', 'contract_value' => '52400.0000',  'contract_length' => 36, 'risk_score' => '3.50'],
            ['customer_name' => 'Sunrise Hotels Group',           'annual_usage' => '495000.0000', 'contract_value' => '89500.0000',  'contract_length' => 48, 'risk_score' => '5.00'],
            ['customer_name' => 'Meridian Logistics plc',         'annual_usage' => '172000.0000', 'contract_value' => '31800.0000',  'contract_length' => 24, 'risk_score' => '2.75'],
            ['customer_name' => 'Crestwood Retail Centres',       'annual_usage' => '630000.0000', 'contract_value' => '114000.0000', 'contract_length' => 60, 'risk_score' => '6.25'],
            ['customer_name' => 'Hartfield NHS Foundation Trust',  'annual_usage' => '920000.0000', 'contract_value' => '167500.0000', 'contract_length' => 48, 'risk_score' => '1.50'],
            ['customer_name' => 'Ashbrook Data Centres Ltd',      'annual_usage' => '1450000.0000','contract_value' => '263000.0000', 'contract_length' => 60, 'risk_score' => '4.00'],
            ['customer_name' => 'Elmfield Brewing Co.',           'annual_usage' => '98000.0000',  'contract_value' => '18200.0000',  'contract_length' => 12, 'risk_score' => '7.00'],
            ['customer_name' => 'Pinnacle Cold Storage Ltd',      'annual_usage' => '345000.0000', 'contract_value' => '63500.0000',  'contract_length' => 36, 'risk_score' => '3.25'],
            ['customer_name' => 'Granville Pharmaceuticals',      'annual_usage' => '520000.0000', 'contract_value' => '95000.0000',  'contract_length' => 48, 'risk_score' => '2.00'],
            ['customer_name' => 'Hadley Ford Dealerships',        'annual_usage' => '76000.0000',  'contract_value' => '14100.0000',  'contract_length' => 24, 'risk_score' => '5.75'],
            ['customer_name' => 'Redbridge University',           'annual_usage' => '1100000.0000','contract_value' => '199000.0000', 'contract_length' => 60, 'risk_score' => '1.00'],
            ['customer_name' => 'Clearwater Leisure Parks',       'annual_usage' => '215000.0000', 'contract_value' => '39800.0000',  'contract_length' => 36, 'risk_score' => '4.50'],
            ['customer_name' => 'Foxbourne Car Manufacturing',    'annual_usage' => '870000.0000', 'contract_value' => '157000.0000', 'contract_length' => 48, 'risk_score' => '6.00'],
            ['customer_name' => 'Oakwell Supermarkets Ltd',       'annual_usage' => '390000.0000', 'contract_value' => '71500.0000',  'contract_length' => 36, 'risk_score' => '3.00'],
            ['customer_name' => 'Tidewater Marine Services',      'annual_usage' => '142000.0000', 'contract_value' => '26300.0000',  'contract_length' => 24, 'risk_score' => '8.50'],
        ];

        foreach ($contracts as $contract) {
            Contract::create($contract);
        }
    }

    private function seedFormulas(): void
    {
        // ast_json is a placeholder — the real parser populates this in a later commit.
        Formula::create([
            'name'       => 'Standard Commission',
            'version'    => 1,
            'expression' => 'AnnualUsage * 0.05',
            'ast_json'   => [],
            'status'     => 'draft',
            'created_by' => 'admin@energylogix.com',
        ]);

        Formula::create([
            'name'       => 'Premium Commission',
            'version'    => 1,
            'expression' => '(AnnualUsage * 0.05) + (ContractLength * 100)',
            'ast_json'   => [],
            'status'     => 'draft',
            'created_by' => 'admin@energylogix.com',
        ]);
    }
}
