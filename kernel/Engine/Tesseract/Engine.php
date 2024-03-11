<?php
namespace Manomite\Engine\Tesseract;

use \thiagoalessio\TesseractOCR\TesseractOCR;
use \Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use \Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;

class Engine
{

    private $response;
    public function extract($image)
    {
        $this->response = (new TesseractOCR($image))->run();
        return $this;
    }

    public function ID_Man()
    {
        $config = new Gpt3TokenizerConfig();
        $tokenizer = new Gpt3Tokenizer($config);
        $numberOfTokens += $tokenizer->count($this->response);

        if (!empty($this->response) and $numberOfTokens >= 150) {
            $mess = array();
            $mess[] = array('role' => 'system', 'content' => 'You are called Mira. Extract valuable informations from text and label them with an appropriate or key_names for their respective values. Convert your results into flattened lines. Each line should have its own label and value. For example (Name : Value). The value should be separated with :. Only return the quality informations as response without adding explanations. Dont include Holders_Signature or anything containing signature. Also for labels having whitespace, use _ to replace the space, only for the labels. Dont use _ for the values, leave the whitespace for that. Informations that are not valuable should be discarded and not be included. Remove all tags from contents and return a clean output that can be looped through line by line.');
            $mess[] = array('role' => 'user', 'content' => $this->response);
            $res = $this->mira($mess);
            if (isset($res->choices[0]->message->content)) {
                if (!empty($res->choices[0]->message->content)) {
                    $response = $res->choices[0]->message->content;
                    $fp = fopen("php://memory", 'r+');
                    fputs($fp, $response);
                    rewind($fp);
                    $realData = array();
                    while ($line = fgets($fp)) {
                        $line = explode(' : ', $line);
                        $realData[str_replace(' ', '_', strtolower($line[0]))] = $line[1];
                    }
                    return $realData;
                }
            }
        }
        return false;
    }

    private function mira(array $message)
    {
        $openaiClient = \Tectalic\OpenAi\Manager::build(
            new \GuzzleHttp\Client(),
            new \Tectalic\OpenAi\Authentication(CONFIG->get('openai-key'))
        );
        $response = $openaiClient->chatCompletions()->create(
            new \Tectalic\OpenAi\Models\ChatCompletions\CreateRequest([
                'model' => 'gpt-3.5-turbo-16k',
                'messages' =>
                    $message
                ,
            ])
        )->toModel();
        return $response;
    }
}