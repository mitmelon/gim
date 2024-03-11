<?php
namespace Manomite\Engine\Tesseract;
use \Carbon\{
    Carbon,
    CarbonInterval
};

class ID {

    private $text;
    public function __construct($text){
        $this->text = $text;
    }

    public function extract() {
        // Define patterns for common ID attributes
        
        $patterns = [
            'name' => '/(Name|Full|Surname\s*Name):\s*([\w\s-]+)/i',
            'age' => '/Age:\s*(\d+)/i',
            'dob' => '/Date\s*of\s*Birth:\s*([\d\/-]+)/i',
            'id_type' => '/ID\s*Type:\s*([\w\s-]+)/i',
            'id_number' => '/ID\s*Number:\s*([\w\d]+)/i',
            'date_issued' => '/Date\s*Issued:\s*([\d\/-]+)/i',
            'expiry_date' => '/Expiry\s*Date:\s*([\d\/-]+)/i',
            'issue_authority' => '/(Issuer\s*Authority|Authority|Issuer):\s*([\w\s-]+)/i',
            'nin' => '/NIN:\s*([\w\d]+)/i',
        ];
    
        // Initialize an array to store extracted information
        $extractedData = [];
    
        // Iterate through patterns and attempt to extract information
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $this->text, $matches)) {
                // Use the first capturing group as the extracted value
                $extractedData[strtolower(str_replace(' ', '_', $key))] = $matches[1];
            }
        }
    
        // Include additional information not covered by specific patterns
        // Adjust these as needed based on the likely content of your ID cards
        $additionalInfo = [
            'gender' => '/Gender:\s*([\w\s-]+)/i',
            'address' => '/Address:\s*([\w\d\s,-]+)/i',
        ];
    
        foreach ($additionalInfo as $key => $pattern) {
            if (preg_match($pattern, $this->text, $matches)) {
                $extractedData[strtolower(str_replace(' ', '_', $key))] = $matches[1];
            }
        }

         // Extract ID type, issuer country, and authority
         $idTypePattern = '/(Passport|Driver\s*License|ID\s*Card|Residency\s*Card)/i';
         if (preg_match($idTypePattern, $this->text, $matches)) {
             $extractedData['id_type'] = $matches[1];
         }
     
         $issuerCountryPattern = '/Issuer\s*Country:\s*([\w\s-]+)/i';
         if (preg_match($issuerCountryPattern, $this->text, $matches)) {
             $extractedData['issuer_country'] = $matches[1];
         }
     
         $issuerAuthorityPattern = '/(Issuer\s*Authority|Authority|Issuer):\s*([\w\s-]+)/i';
         if (preg_match($issuerAuthorityPattern, $this->text, $matches)) {
             $extractedData['issuer_authority'] = $matches[2];
         }
     
         // Extract NIN (National Identification Number) pattern
         $ninPattern = '/NIN:\s*([\w\d]+)/i';
         if (preg_match($ninPattern, $this->text, $matches)) {
             $extractedData['nin'] = $matches[1];
         }

         $promisingPattern = '/(Place\s*of\s*Birth|Nationality|Occupation|Additional\s*Info):\s*([\w\s-]+)/i';
        if (preg_match_all($promisingPattern, $this->text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // Use the second capturing group as the extracted value
                $extractedData[strtolower(str_replace(' ', '_', $match[1]))] = $match[2];
            }
        }
        return $extractedData;
    }

    public function matchInformationWithProvider($extractedData, $providerArray) {
        $matchedData = [];
    
        // Count the total number of keys in the provider array
        $totalKeys = count($providerArray);
    
        // Initialize a counter for matching keys
        $matchingKeys = 0;
    
        // Iterate through the extracted data
        foreach ($providerArray as $key => $value) {
            foreach ($extractedData as $k => $v) {
                // Check if the extracted value matches the provider value
                if($this->isDate($value)){
                    date_default_timezone_set(TIMEZONE);
                    $carbon = Carbon::now(TIMEZONE);
                    $value = $carbon->parse($value)->format('d-m-Y');
                }
                if($this->isDate($v)){
                    date_default_timezone_set(TIMEZONE);
                    $carbon = Carbon::now(TIMEZONE);
                    $v = $carbon->parse($v)->format('d-m-Y');
                }
                if ($this->contains($value, $v) === 0) {
                    $matchingKeys++;
                    $matchedData[$key] = $value;
                }
            }
        }
        // Calculate the percentage of matching keys
        $matchingPercentage = ($matchingKeys / $totalKeys) * 100;
    
        return ['matched_data' => $matchedData, 'matching_percentage' => $matchingPercentage];
    }

    private function contains($haystack, $needle, $caseSensitive = false)
    {
        return $caseSensitive ?
            (strpos($haystack, $needle) === FALSE ? FALSE : TRUE) :
            (stripos($haystack, $needle) === FALSE ? FALSE : TRUE);
    }

    private function isDate($string){
        try {
            $date = @Carbon::parse($string);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }


  


    
    

}
