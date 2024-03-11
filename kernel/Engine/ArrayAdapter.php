<?php
namespace Manomite\Engine;

class ArrayAdapter
{
    public function array_replace(array $array, string $find, string $replace)
    {
        if (is_array($array)) {
            foreach ($array as $key => $val) {
                if (is_array($array[$key])) {
                    $array[$key] = $this->array_replace($array[$key], $find, $replace);
                } else {
                    if ($key == $find) {
                        $array[$key] = $replace;
                    }
                }
            }
        }
        return $array;
    }
    public function array_find(string $needle, array $haystack)
    {
        if (is_array($haystack)) { // check for multidimentional array
                foreach ($haystack as $key => $value) { // for normal array
                    if ($needle === $key || $needle === $value) {
                        return array($key, $value);
                    }
                }
        }
        return false;
    }
    public function array_find_dimen(string $needle, array $haystack, string $column = null)
    {
        if (is_array($haystack[0]) === true) { // check for multidimentional array
            foreach (array_column($haystack, $column) as $key => $value) {
                if (strpos(strtolower($value), strtolower($needle)) !== false) {
                    return array($key, $value);
                }
            }
        }
        return false;
    }

    public function remove_element(array $array, string $remove)
    {
        foreach (array_keys($array, $remove) as $key) {
            unset($array[$key]);
        }
        return $array;
    }
    public function array_flatten(array $array)
    {
        if (!is_array($array)) {
            return false;
        }
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->array_flatten($value));
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
    public function needles_exist(string $needles, array $haystack)
    {
        if (count(array_intersect_key(array_flip($needles), $haystack)) === count($needles)) {
            return true;
        }
    }
    public function obj_to_array(object $obj, &$arr)
    {
        if (!is_object($obj) && !is_array($obj)) {
            $arr = $obj;
            return $arr;
        }
    
        foreach ($obj as $key => $value) {
            if (!empty($value)) {
                $arr[$key] = array();
                $this->obj_to_array($value, $arr[$key]);
            } else {
                $arr[$key] = $value;
            }
        }
        return $arr;
    }

    public function multi_implode($data, $tag)
    {
        if (!is_array($data)) {
            return false;
        }
        implode(', ', array_map(function ($entry) use ($tag) {
            return $entry[$tag];
        }, $data));
    }

    public function is_multi_array($arr)
    {
        rsort($arr);
        return isset($arr[0]) && is_array($arr[0]);
    }

    public function check_keys_exists($keys_str = "", $arr = array()){
        $return = false;
        if($keys_str != "" and !empty($arr)){
            $keys = explode(', ', $keys_str);
            if(!empty($keys)){
                foreach($keys as $key){
                    $return = array_key_exists($key, $arr);
                    if($return == false){
                        break;
                    }
                }
            }
        }
        return $return;
    }

    /**
     * Get a random value from an array.
     *
     * @param array $array
     * @param int   $numReq The amount of values to return
     *
     * @return mixed
     */
    public function array_rand_value(array $array, $numReq = 1)
    {
        if (! count($array)) {
            return;
        }

        $keys = array_rand($array, $numReq);

        if ($numReq === 1) {
            return $array[$keys];
        }

        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Get a random value from an array, with the ability to skew the results.
     * Example: array_rand_weighted(['foo' => 1, 'bar' => 2]) has a 66% chance of returning bar.
     *
     * @param array $array
     *
     * @return mixed
     */
    public function array_rand_weighted(array $array)
    {
        $array = array_filter($array, function ($item) {
            return $item >= 1;
        });

        if (! count($array)) {
            return;
        }
        $totalWeight = array_sum($array);

        foreach ($array as $value => $weight) {
            if (rand(1, $totalWeight) <= $weight) {
                return $value;
            }
            $totalWeight -= $weight;
        }
    }

    /**
     * Determine if all given needles are present in the haystack.
     *
     * @param array|string $needles
     * @param array        $haystack
     *
     * @return bool
     */
    public function values_in_array($needles, array $haystack)
    {
        if (! is_array($needles)) {
            $needles = [$needles];
        }

        return count(array_intersect($needles, $haystack)) === count($needles);
    }

    /**
     * Determine if all given needles are present in the haystack as array keys.
     *
     * @param array|string $needles
     * @param array        $haystack
     *
     * @return bool
     */
    public function array_keys_exist($needles, array $haystack)
    {
        if (! is_array($needles)) {
            return array_key_exists($needles, $haystack);
        }

        return values_in_array($needles, array_keys($haystack));
    }

    /**
     * Returns an array with two elements.
     *
     * Iterates over each value in the array passing them to the callback function.
     * If the callback function returns true, the current value from array is returned in the first
     * element of result array. If not, it is return in the second element of result array.
     *
     * Array keys are preserved.
     *
     * @param array    $array
     * @param callable $callback
     *
     * @return array
     */
    public function array_split_filter(array $array, callable $callback)
    {
        $passesFilter = array_filter($array, $callback);

        $negatedCallback = static function ($item) use ($callback) {
            return ! $callback($item);
        };

        $doesNotPassFilter = array_filter($array, $negatedCallback);

        return [$passesFilter, $doesNotPassFilter];
    }

    /**
     * Split an array in the given amount of pieces.
     *
     * @param array $array
     * @param int   $numberOfPieces
     * @param bool  $preserveKeys
     * @throws \InvalidArgumentException if the provided argument $numberOfPieces is lower than 1
     *
     * @return array
     */
    public function array_split(array $array, $numberOfPieces = 2, $preserveKeys = false)
    {
        if ($numberOfPieces <= 0) {
            throw new \InvalidArgumentException('Number of pieces parameter expected to be greater than 0');
        }

        if (count($array) === 0) {
            return [];
        }

        $splitSize = ceil(count($array) / $numberOfPieces);

        return array_chunk($array, $splitSize, $preserveKeys);
    }

    /**
     * Returns an array with the unique values from all the given arrays.
     *
     * @param \array[] $arrays
     *
     * @return array
     */
    public function array_merge_values(array ...$arrays)
    {
        $allValues = array_reduce($arrays, static function ($carry, $array) {
            return array_merge($carry, $array);
        }, []);

        return array_values(array_unique($allValues));
    }
}