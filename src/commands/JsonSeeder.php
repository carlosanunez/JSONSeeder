<?php

namespace Filipekiss\JsonSeeder\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class JsonSeeder extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'jsonseeder';
	var $className, $tableName, $modelName, $callingPath, $seedPath, $files, $JSONSeederPath;

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generates a DBSeed based on a JSON file.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{

		$this->excludeKeys = $this->option('exclude-key');

		$this->definePaths();

		$this->verifyFiles();

		$this->confirmSeedPath();

		if( !is_writable($this->seedPath) ){
			echo "\n";
			$message = $this->buildPanel( ' '.$this->seedPath." is not writable. Please, change the appropriate permissions or use another directory " , 'error' );
			$this->error( $message );
			exit(1);
		}

		$this->generateSeeds( $this->files );


	}

	public function definePaths(){
		$this->callingPath = realpath( dirname( $_SERVER['SCRIPT_FILENAME'] ) );
		$this->seedPath = $this->callingPath . '/app/database/seeds';
		$this->JSONSeederPath = dirname(__FILE__);
		$this->stubsPath =  realpath( $this->JSONSeederPath.'/../stubs' );
	}

	public function verifyFiles(){
		$files = $this->option('file');
		if( empty( $files ) ){
			echo "\n\n";
			$message = $this->buildPanel('  Usage: php artisan JSONSeeder -f file.json  ' , 'question');
			$this->error( $message );
			echo "\n\n";
			exit(1);
		}
		$this->files = $files;
		if( count( $this->files ) == 1){
			$this->className = $this->option('className');
			$this->modelName = $this->option('modelName');
			$this->tableName = $this->option('tableName');
		}
	}

	public function confirmSeedPath(){

		if( $this->option('default-path') )
			return true;

		$this->buildPanel( " JSONSeeder will put your seed in the following directory: ".$this->seedPath."/ " , 'comment' );

		$message = ' Is it right? [Y/n] ';
		if( ! $this->confirm( $message , true) ){
			echo "\n";
			switch ( $this->confirm( ' Do you wish to provide a path relative to '.$this->callingPath.' ? [Y/n] ' , true) ) {
				case true:
					$confirmedPath = false;
					while( !$confirmedPath ):
						echo "\n";
						$appendPath = $this->ask('Please, input the path relative to '.$this->callingPath.'/');
						$this->seedPath = realpath( $this->callingPath.'/'.$appendPath );
						$this->buildPanel( $this->seedPath , 'info');
						echo "\n";
						$confirmedPath = $this->confirm( 'Is the path above right? [y/N]' , false );
					endwhile;
				break;
				case false:
					$confirmedPath = false;
					while( !$confirmedPath ):
						echo "\n";
						$appendPath = $this->ask('Please, input the absolute path:');
						$this->seedPath = $appendPath;
						$this->buildPanel( $this->seedPath , 'info' );
						echo "\n";
						$confirmedPath = $this->confirm( 'Is the path above right? [y/N]' , false );
					endwhile;
				break;
			}
		}
	}

	public function generateSeeds( $files = '' ){
		foreach( $files as $file ){
			$className = $this->generateClassName( $file );
			$this->buildPanel(' Generating seed for '.basename($file).' - '.$className . ' ' , 'question' );
			$this->seederDetails( $className );
			$template = file_get_contents($this->stubsPath.'/DBSeeder.template.php');
			$replace = array( ':className' => $this->className , ':tableName' => $this->tableName, ':modelName' => $this->modelName );
			$output = str_replace(array_keys($replace), array_values($replace), $template);
			$seedBlockTemplate = $this->getSeedBlock( $output );
			$seeds = $this->generateSeed( $file , $seedBlockTemplate );
			$seedsString = array();
			foreach( $seeds as $seed ){
				$modelDetails = var_export($seed , true);
				$seedsString[] = str_replace(':modelDetails', $modelDetails, $seedBlockTemplate);
			}
			$clean_output = $this->generateOutput( $output , $seedsString );
			$filename = $this->seedPath.'/'.$this->className.'.php';
			$saved = file_put_contents( $filename , $clean_output);
			if( $saved ){
				$this->buildPanel( 'Seed saved to '.$filename );
			}else{
				$this->buildPanel( 'An error ocurred when saving data to '.$filename , 'error');
				exit(1);
			}
			$this->className = '';
			$this->tableName = '';
			$this->modelName = '';
		}
	}

	public function generateOutput( $current_output = '' , $seeds ){
		$output = $current_output;
		$seedBlock = stristr($output, ':BeginSeedBlock');
		$seedBlock = str_replace(stristr($seedBlock , ':EndSeedBlock'), ':EndSeedBlock', $seedBlock);
		$output =str_replace($seedBlock, join( "\n\t\t" , $seeds ), $output);
		return $output;
	}

	public function generateSeed( $file = '' , $seedBlockTemplate = ''){
		$json = json_decode( file_get_contents( $this->callingPath.'/'.$file ) );
		$dataset = array();
		foreach( $json as $id => $details ){
			$data = array();
			if( isset( $details->id ) )
				$id = $details->id;
			$data['id'] = $id;
			foreach( $details as $key => $val ){
				if( ! in_array($key, $this->excludeKeys) )
					$data[$key] = $val;
			}
			$dataset[] = $data;
		}
		return $dataset;
	}

	public function seederDetails( $className ){
		while( !$this->className ):
			$this->className = $this->ask( ' The class name to give to this Seeder [Default - ' .$className. ']: ' , $className);
		endwhile; //:className
		while( !$this->modelName ):
			$this->modelName = $this->ask( ' The model name to use with this dataset [Ex: User]: ' );
		endwhile; //:className
		while( !$this->tableName ):
			$this->tableName = $this->ask( ' The table name to use with this dataset [Ex: my_table]:' );
		endwhile; //:className
	}

	public function buildPanel( $message , $type = 'info') {
		echo "\n";
		$this->{$type}( str_repeat(' ', strlen( $message ) ) );
		$this->{$type}( $message );
		$this->{$type}( str_repeat(' ', strlen( $message ) ) );
		echo "\n";
	}

	public function generateClassName( $fileName =  '' ){
		if( !$fileName )
			return false;
		$fileName = basename($fileName);
		$className = str_replace('.', '_', $fileName);
		$func = create_function('$c', 'return strtoupper($c[1]);');
		$className = preg_replace_callback('/_([a-z])/', $func, $className);
		return $className.time();
	}

	public function getSeedBlock( $template = '' ){
		$beginSeed = stripos($template, ':BeginSeedBlock') + strlen(':BeginSeedBlock');
		$endSeed = stripos($template, ':EndSeedBlock');
		$seedTemplate = substr($template, $beginSeed, $endSeed - $beginSeed);
		$seedTemplate = preg_replace('/\s+/', ' ', $seedTemplate);
		$seedTemplate = trim( $seedTemplate );
		return $seedTemplate;
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			// array('json', InputArgument::REQUIRED, 'The path to the JSON file'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('file', 'f', InputOption::VALUE_REQUIRED + InputOption::VALUE_IS_ARRAY, 'The JSON file used to generate the seed. If multiple values are passed, each one will generate a different seed.', null),
			array('className', null, InputOption::VALUE_OPTIONAL, 'Optional: The class name to this Seed. If not passed, user will be interactively asked. Does NOT work with multiple files.', null),
			array('modelName', null, InputOption::VALUE_OPTIONAL, 'Optional: The model name to this Seed. If not passed, user will be interactively asked. Does NOT work with multiple files.', null),
			array('tableName', null, InputOption::VALUE_OPTIONAL, 'Optional: The table name to this Seed. If not passed, user will be interactively asked. Does NOT work with multiple files.', null),
			array('default-path', null, InputOption::VALUE_NONE, 'If this option is passed, JSONSeeder will NOT confirm the path where to save the seeds before saving it.', null),
			array('exclude-key', null, InputOption::VALUE_OPTIONAL + InputOption::VALUE_IS_ARRAY, 'Keys to ignore from the JSON when building the seed. You may pass more than one.', null),

		);
	}

}