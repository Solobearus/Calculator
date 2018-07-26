<?php

/**
     *
     * start date : 16:20 20.7.18
     * author :     Ivan Solobear
     * 
     * 
     */
require "vendor/autoload.php";


class Calculator
{
    public static $MATCH_OPERATORS_REGEX_PATTERN = "/([\+\-\/\*\(\)])/";
    public static $MATCH_DEVIDE_MULTIPLY_REGEX_PATTERN = "/([\/\*])/";
    public static $MATCH_ADD_SUBTRUCT_REGEX_PATTERN = "/([\-\+])/";

    public static $DONT_CAP_PROCESS_EXPRESSION_ARRAY_LENGTH_FLAG = -1;
    public static $NUMBER_OF_OPERATIONS_SUPPORTED = 2;

    private $expression_cached = false;
    private $parentheses_count = 0;
    private $expressions_array;
    private $expressions_string_no_whitespace;
    private $expressions_string;
    private $inner_result;
    
    private $redis;


    public function __construct($raw_expressions_string)
    {
        $datetime1 = new DateTime();
        // echo $datetime1->format('s') . "\n";

        $this->getPredis();
        // var_dump($this->redis);
        $this->expressions_string = $raw_expressions_string;
        $this->preprocess();
        
        // echo "sdaf".var_dump($this->expression_cached);
        if($this->expression_cached == false)
        {
            $this->calculate();
            $datetime2 = new DateTime();
            $datetaken = $datetime1->diff($datetime2);
            echo "time taken : ".$datetaken->format('%f') . "\n";
        }
        else
        {
            echo "this came from cache :".$this->redis->get($this->expressions_string_no_whitespace);
            $datetime2 = new DateTime();
            $datetaken = $datetime1->diff($datetime2);
            echo "time taken : ".$datetaken->format('%f') . "\n";
        }
    }


    /**
     *
     * This function calls function process_expression_string to turn the string to a ready array for process expressions_array
     * and than also calls count_parentheses in order to find out the number of parenthesis there are in the expression.
     *
     */
    private function preprocess() {
        $this->process_expression_string();
        if($this->expression_cached == false)
            $this->count_parentheses();
    }


    /**
     *
     * This function initially removes all whitespaces from the raw input string and then
     * it splits it by basic arithematic operators(+,-,*,/), a regex string.
     * By using `PREG_SPLIT_DELIM_CAPTURE` flag we reassure that we receive the operators
     * as well in the retrieved array
     *
     */
    private function process_expression_string()
    {
        $this->expressions_string_no_whitespace = str_replace(' ', '', $this->expressions_string);
        // echo $this->redis->exists($this->expressions_string_no_whitespace);
        if($this->redis->exists($this->expressions_string_no_whitespace) == 1)
        {
            $this->expression_cached = true;
        }else{
            $this->expressions_array = preg_split(Calculator::$MATCH_OPERATORS_REGEX_PATTERN, $this->expressions_string_no_whitespace, 
            Calculator::$DONT_CAP_PROCESS_EXPRESSION_ARRAY_LENGTH_FLAG, PREG_SPLIT_DELIM_CAPTURE);
            
            // Validation for whitespace/empty array elements removal
            for($i=0 ; $i<count($this->expressions_array) ; $i++)
            {
                if($this->expressions_array[$i]=="")
                array_splice($this->expressions_array,$i,1);
            }
            
        }
    }

    /**
     *
     * This function counts the amount of $opening_parentheses and $closing_parentheses in the expressions_array
     * checks that they both match in amount and also no $closing_parentheses comes before matching $opening_parentheses
     * than it updates $this->parentheses_count. 
     * 
     * @return bool valid or not valid.
     */
    private function count_parentheses()
    {
        
        $opening_parentheses = 0;
        $closing_parentheses = 0;
        for($i=0 ; $i<count($this->expressions_array) ; $i++)
        {
            if ($closing_parentheses > $opening_parentheses)
                return false;

            if($this->expressions_array[$i] == "(") $opening_parentheses++;
            if($this->expressions_array[$i] == ")") $closing_parentheses++;

        }
        if ($opening_parentheses == $closing_parentheses)
            $this->parentheses_count = $opening_parentheses;
        else
            return false;
    } 

    /**
     *
     * This function counts the amount of $opening_parentheses and $closing_parentheses in the expressions_array
     * checks that they both match in amount and also no $closing_parentheses comes before matching $opening_parentheses
     * than it updates $this->parentheses_count. 
     * 
     * @return bool valid or not valid.
     */

    private function proccess_parentheses()
    {
        $index_of_last_opening_parentheses = 0;
        
        for($i=0 ; $i<$this->parentheses_count ; $i++)
        {
            for($j=0 ; $j<count($this->expressions_array) ; $j++)
            {
                if($this->expressions_array[$j] == "(")
                {
                    $index_of_last_opening_parentheses = $j;
                }
                elseif($this->expressions_array[$j] == ")")
                {
                    $this->expressions_sub_array = array_slice($this->expressions_array,$index_of_last_opening_parentheses+1,$j-$index_of_last_opening_parentheses-1);
                    
                    if($this->isValid($this->expressions_sub_array))
                    {
                        $this->calculate_single_expression($this->expressions_sub_array);
                        array_splice($this->expressions_array,$index_of_last_opening_parentheses,$j-$index_of_last_opening_parentheses+1,$this->expressions_sub_array);
                    }
                }
            }
        }
    }


    /**
     *
     * This function initially removes all whitespaces from the raw input string and then
     * it splits it by basic arithematic operators(+,-,*,/), a regex string.
     * By using `PREG_SPLIT_DELIM_CAPTURE` flag we reassure that we receive the operators
     * as well in the retrieved array
     *
     * @param string preproccessed calculator raw input.
     *
     * @return array string as array with no whitespaces(where each number has its own slot in the array)
     */
    private function calculate()
    {
        // echo $this->parentheses_count;
        if($this->parentheses_count !== false) 
        {
            $this->proccess_parentheses($this->expressions_array,$this->parentheses_count);
            
            // echo var_dump($this->isValid($this->expressions_array));
            if($this->isValid($this->expressions_array)){
                $this->expressions_sub_array = $this->expressions_array;
                $this->calculate_single_expression();
                // echo $this->expressions_string_no_whitespace;
                $this->redis->set($this->expressions_string_no_whitespace,$this->expressions_sub_array[0]);
                echo $this->expressions_sub_array[0];
            }
        }
    }

    
    /**
     *
     * This function takes class's expressions_sub_array which is either one of the inner parethesis or the expression itself
     * after it solved all parethesis and process it.
     * it runs $NUMBER_OF_OPERATIONS_SUPPORTED times on the expressions_sub_array and each iteration it searches for the corresponding
     * operators. than it looks at the two numbers before and after the operator ,calculates the result ,gets rid of the numbers and the operator
     * and puts the result again.
     */

    private function calculate_single_expression()
    {
        // This is the order of operation for loop
        for($i=0 ; $i<Calculator::$NUMBER_OF_OPERATIONS_SUPPORTED ; $i++)
        {
            for($j=0 ; $j<count($this->expressions_sub_array) ; $j++)
            {       
                if( ((preg_match(Calculator::$MATCH_DEVIDE_MULTIPLY_REGEX_PATTERN,$this->expressions_sub_array[$j]) && $i == 0)
                    ||(preg_match(Calculator::$MATCH_ADD_SUBTRUCT_REGEX_PATTERN,$this->expressions_sub_array[$j]) && $i == 1))
                    && strlen($this->expressions_sub_array[$j]) <= 1)
                {
                    switch ($this->expressions_sub_array[$j]) {
                        case '*':
                            $this->expressions_sub_array[$j-1] = $this->expressions_sub_array[$j-1] * $this->expressions_sub_array[$j+1];
                            array_splice($this->expressions_sub_array,$j,2);
                            $j--;
                            break;
                        case '/':
                            $this->expressions_sub_array[$j-1] = $this->expressions_sub_array[$j-1] / $this->expressions_sub_array[$j+1];    
                            array_splice($this->expressions_sub_array,$j,2);
                            $j--;
                            break;
                        case '-':
                            $this->expressions_sub_array[$j-1] = $this->expressions_sub_array[$j-1] - $this->expressions_sub_array[$j+1];
                            array_splice($this->expressions_sub_array,$j,2);
                            $j--;
                            break;
                        case '+':
                            $this->expressions_sub_array[$j-1] = $this->expressions_sub_array[$j-1] + $this->expressions_sub_array[$j+1];    
                            array_splice($this->expressions_sub_array,$j,2);
                            $j--;
                            break;
                        default:
                            echo "regex screwed something up yo";
                            break;
                    }
                    // echo print_r($this->expressions_array,1);
                }
            }
        }
    }

    /**
     *
     * This function checks if the array is valid in terms of math expression
     * no two operators can exist one after the other.
     * no two numbers can exist one after the other.
     * 
     * @param array the array for the check.
     *
     * @return bool valid or not valid.
     */
    private function isValid($input_array)
    {
        $munis_flag = ($input_array[0] == "-")?1:0;
        // echo "munis_flag : $munis_flag";
        for($i=0 ; $i<count($input_array) ; $i++)
        {
            if($i%2+$munis_flag == 0)
            {
                if(!is_numeric($input_array[$i]))
                {
                    echo "the element in index $i of the input or one of your parentheses suppost to be a numeric value";
                    return false;
                }
            }else{
                // NOTE: Regex use.
                if(!preg_match("/([\+\-\/\*])/",$input_array[$i]))
                {
                    echo "the element in index $i of the input or one of your parentheses suppost to be a operator";
                    return false;
                }
            }
        }
        return true;
    }

    
    /**
     *
     * This function tries to connect to predis which is redis's php extension.
     */

    private function getPredis()
    {
        // PredisAutoloader::register();
        try {
            $this->redis = new Predis\Client();;
            // var_dump($this->redis);
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }
//This was all the tests I ran in order to understand how to work with preg_split
//((\d)+)
//[\+\-\/\*]
//((\d)+|[\+\-|/\*]|(\d)+)
// echo 'Hello ' . htmlspecialchars($_POST["preproccessed"]) . '!';
// preg_match_all('[\+\-\/\*]',$input_string,$input_string_post_split,PREG_SPLIT_DELIM_CAPTURE);
// echo var_dump($input_string_post_split);
}