<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            UserSeeder::class,
            RoleSeeder::class,
            ActionSeeder::class,
            AdministratorAccountSeeder::class,
            ExamTypeSeeder::class,
        ]);
    }
}
