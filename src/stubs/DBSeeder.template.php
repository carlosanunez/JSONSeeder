<?php

class :className extends Seeder {

	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		DB::table(':tableName')->delete();
		:BeginSeedBlock
			:modelName::create(:modelDetails);
		:EndSeedBlock
	}

}