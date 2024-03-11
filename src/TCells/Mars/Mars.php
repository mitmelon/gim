<?php
namespace Manomite\TCells\Mars;
use \Manomite\Engine\Bayes\Classifier;
use \Manomite\Engine\Bayes\Tokenizer\WhitespaceAndPunctuationTokenizer;

require_once __DIR__."/../../../autoload.php";

class Mars
{
    public function __construct($model = null)
    {
        $this->model = $model;
        if($model === null){
            //auto select latest model
            $files = scandir(__DIR__.'/model', SCANDIR_SORT_DESCENDING);
            $this->model = __DIR__.'/model/'.$files[0];
        }
        $tokenizer = new WhitespaceAndPunctuationTokenizer();
        $this->classifier = new Classifier($tokenizer);
    }

    public function trainBot($label, array $text, $autogenemodel = 'on'):void
    {
        $text =  json_encode($text);
        $csvFile = fopen(__DIR__.'/data/training.csv', "a");
        fputcsv($csvFile, array($label, $text));
        if ($autogenemodel === 'on') {
            $this->generateTrainedModel();
        }
    }

    public function predict(array $text):array
    {
        try {
            $text =  json_encode($text);
            return $this->classifier->classifyFromModel($text, $this->model);
        } catch(\Exception $e){
            //Auto repair
            $this->generateTrainedModel();
            return $this->predict($text);
        }
    }

    private function generateTrainedModel():void
    {
        //Training model
        $csvFile = fopen(__DIR__.'/data/training.csv', 'r');
        while (($line = fgetcsv($csvFile)) !== false) {
            $this->classifier->train($line[0], $line[1]);
        }
        //clean model folder
        $files = scandir(__DIR__.'/model', SCANDIR_SORT_DESCENDING);
        if (count($files) > 2) {
            foreach ($files as $file) {
                @unlink(__DIR__.'/model/'.$file);
            }
        }
        $this->classifier->generateModel(__DIR__.'/model', 'fraudModel-v2-'.date('d-m-Y'));
    }

}