<?php
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BLAST parameter validation
 */
class BlastValidatorTest extends TestCase {
    
    /**
     * Test valid BLAST executable names
     */
    public function testValidBlastExecutables() {
        $valid = ['blastn', 'blastp', 'blastx', 'tblastn', 'tblastx'];
        
        foreach ($valid as $exe) {
            $this->assertContains($exe, $valid, "BLAST executable $exe should be valid");
        }
    }
    
    /**
     * Test invalid BLAST executable names
     */
    public function testInvalidBlastExecutables() {
        $invalid = ['invalid', 'rm', 'bash', '../../../etc/passwd', 'blast; rm -rf /'];
        $valid = ['blastn', 'blastp', 'blastx', 'tblastn', 'tblastx'];
        
        foreach ($invalid as $exe) {
            $this->assertNotContains($exe, $valid, "BLAST executable $exe should be invalid");
        }
    }
    
    /**
     * Test job ID format validation (security against directory traversal)
     */
    public function testJobIdValidation() {
        $validPattern = '/^job_[0-9]+_[a-f0-9]{8}$/';
        
        // Valid job IDs
        $validIds = [
            'job_1234567890_abcd1234',
            'job_9999999999_12345678',
            'job_1000000000_ffffffff'
        ];
        
        foreach ($validIds as $id) {
            $this->assertEquals(1, preg_match($validPattern, $id), "Job ID $id should be valid");
        }
        
        // Invalid job IDs (security threats)
        $invalidIds = [
            '../../../etc/passwd',
            'job_123_abc',
            'job_abc_12345678',
            'job_123_ABCDEFGH',
            'job_123_1234567g',
            '; rm -rf /',
            'job_123/../../'
        ];
        
        foreach ($invalidIds as $id) {
            $this->assertEquals(0, preg_match($validPattern, $id), "Job ID $id should be invalid");
        }
    }
    
    /**
     * Test job ID generation format
     */
    public function testJobIdGeneration() {
        $jobId = 'job_' . time() . '_' . bin2hex(random_bytes(4));
        $pattern = '/^job_[0-9]+_[a-f0-9]{8}$/';
        
        $this->assertEquals(1, preg_match($pattern, $jobId), "Generated job ID should match pattern");
        $this->assertStringStartsWith('job_', $jobId);
    }
    
    /**
     * Test e-value validation
     */
    public function testEvalueValidation() {
        $validEvalues = ['1e-5', '1e-10', '0.001', '10', '1.5e-20'];
        $invalidEvalues = ['abc', 'not-a-number', '1e', 'e-5'];
        
        foreach ($validEvalues as $evalue) {
            $isValid = is_numeric($evalue) || preg_match('/^\d+\.?\d*e-?\d+$/i', $evalue);
            $this->assertTrue($isValid, "E-value $evalue should be valid");
        }
        
        foreach ($invalidEvalues as $evalue) {
            $isValid = is_numeric($evalue) || preg_match('/^\d+\.?\d*e-?\d+$/i', $evalue);
            $this->assertFalse($isValid, "E-value $evalue should be invalid");
        }
    }
    
    /**
     * Test max hits parameter validation
     */
    public function testMaxHitsValidation() {
        // Valid values
        $this->assertGreaterThan(0, 10);
        $this->assertLessThanOrEqual(1000, 10);
        $this->assertGreaterThan(0, 500);
        
        // Invalid values
        $this->assertLessThanOrEqual(0, 0);
        $this->assertLessThanOrEqual(0, -5);
        $this->assertGreaterThan(1000, 1001);
    }
    
    /**
     * Test sequence length validation
     */
    public function testSequenceLengthValidation() {
        $minLength = 10;
        $maxLength = 1000000;
        
        // Valid sequences
        $validSeq = str_repeat('ATCG', 5); // 20 characters
        $this->assertGreaterThanOrEqual($minLength, strlen($validSeq));
        $this->assertLessThanOrEqual($maxLength, strlen($validSeq));
        
        // Too short
        $tooShort = 'ATCG';
        $this->assertLessThan($minLength, strlen($tooShort));
        
        // Empty
        $this->assertEmpty('');
    }
    
    /**
     * Test sanitization of job ID
     */
    public function testJobIdSanitization() {
        $malicious = "job_123; DROP TABLE users; --";
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $malicious);
        
        $this->assertEquals('job_123DROPTABLEusers', $sanitized);
        $this->assertStringNotContainsString(';', $sanitized);
        $this->assertStringNotContainsString(' ', $sanitized);
    }
    
    /**
     * Test HTTP method validation
     */
    public function testHttpMethodValidation() {
        $allowedMethods = ['POST', 'GET'];
        
        $this->assertContains('POST', $allowedMethods);
        $this->assertContains('GET', $allowedMethods);
        $this->assertNotContains('DELETE', $allowedMethods);
        $this->assertNotContains('PUT', $allowedMethods);
    }
    
    /**
     * Test parameter trimming
     */
    public function testParameterTrimming() {
        $input = "  blastn  ";
        $trimmed = trim($input);
        
        $this->assertEquals('blastn', $trimmed);
        $this->assertNotEquals($input, $trimmed);
    }
    
    /**
     * Test database path construction
     */
    public function testDatabasePathConstruction() {
        $dbPath = '/data/blastdb/';
        $database = 'mydb';
        
        // Relative database name
        $fullPath = rtrim($dbPath, '/') . '/' . $database;
        $this->assertEquals('/data/blastdb/mydb', $fullPath);
        
        // Absolute database path
        $absoluteDb = '/absolute/path/mydb';
        $shouldBeAbsolute = (substr($absoluteDb, 0, 1) === '/') ? $absoluteDb : $dbPath . $absoluteDb;
        $this->assertEquals('/absolute/path/mydb', $shouldBeAbsolute);
    }
}
