<?php
namespace Manomite\Template;

use \Manomite\Exception\ManomiteException as ex;

require_once __DIR__."/../../autoload.php";
class Text
{
    /**
    	 * The text of the template to load.
    	 *
    	 * @access protected
    	 * @var string
    	 */
    protected $text;
    /**
     * An array of values for replacing each tag on the template (the key for each value is its corresponding tag).
     *
     * @access protected
     * @var array
     */
    protected $values = array();

    /**
     * Creates a new Template object and sets its associated text.
     *
     * @param string $text the text of the template to load
     */
    public function __construct($text)
    {
        $this->text = $text;
    }

    /**
     * Sets a value for replacing a specific tag.
     *
     * @param string $key the name of the tag to replace
     * @param string $value the value to replace
     */
    public function set($key, $value)
    {
        $this->values[$key] = $value;
    }
    /**
     * Outputs the content of the template, replacing the keys for its respective values.
     *
     * @return string
     */
    public function output()
    {
        /**
        	 * Tries to verify if the text exists.
        	 * If it doesn't return with an error message.
        	 * Anything else loads the file contents and loops through the array replacing every key for its value.
        	 */
        $output = $this->text;

        foreach ($this->values as $key => $value) {
            $tagToReplace = "{#$key#}";
            if ($value !== null) {
                if((string)$value === 'Array'){
                    $value = '';
                }
                $output = str_replace($tagToReplace, $value, $output);
            }
        }
        return $output;
    }
    /**
     * Merges the content from an array of templates and separates it with $separator.
     *
     * @param array $templates an array of Template objects to merge
     * @param string $separator the string that is used between each Template object
     * @return string
     */
    public static function merge($templates, $separator = "\n")
    {
        /**
        	 * Loops through the array concatenating the outputs from each template, separating with $separator.
        	 * If a type different from Template is found we provide an error message.
        	 */
        $output = "";

        foreach ($templates as $template) {
            $content = (get_class($template) !== "Text")
                    ? new ex('templateError', 6, 'Sorry! class "Text" is not found.')
                    : $template->output();
            $output .= $content . $separator;
        }

        return $output;
    }
}