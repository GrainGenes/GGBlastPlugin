<?php
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for job management logic
 */
class JobManagerTest extends TestCase {
    
    /**
     * Test job data structure creation
     */
    public function testJobDataCreation() {
        $params = [
            'jobId' => 'job_1234567890_abcd1234',
            'blastexe' => 'blastn',
            'query' => 'ATCGATCGATCG',
            'database' => 'testdb',
            'evalue' => '1e-5',
            'maxHits' => 10,
            'clientIp' => '127.0.0.1'
        ];
        
        $jobData = [
            'jobId' => $params['jobId'],
            'blastexe' => $params['blastexe'],
            'query' => $params['query'],
            'database' => $params['database'],
            'evalue' => $params['evalue'],
            'maxHits' => intval($params['maxHits']),
            'status' => 'pending',
            'created' => date('Y-m-d H:i:s'),
            'clientIp' => $params['clientIp']
        ];
        
        $this->assertArrayHasKey('jobId', $jobData);
        $this->assertArrayHasKey('status', $jobData);
        $this->assertEquals('pending', $jobData['status']);
        $this->assertEquals('blastn', $jobData['blastexe']);
    }
    
    /**
     * Test job path construction
     */
    public function testJobPathConstruction() {
        $jobsPath = '/tmp/jobs';
        $jobId = 'job_1234567890_abcd1234';
        
        $jobDir = $jobsPath . '/' . $jobId;
        $queryFile = $jobDir . '/query.json';
        $resultsFile = $jobDir . '/results.html';
        
        $this->assertEquals('/tmp/jobs/job_1234567890_abcd1234', $jobDir);
        $this->assertEquals('/tmp/jobs/job_1234567890_abcd1234/query.json', $queryFile);
        $this->assertEquals('/tmp/jobs/job_1234567890_abcd1234/results.html', $resultsFile);
    }
    
    /**
     * Test BLAST command building
     */
    public function testBlastCommandBuilding() {
        $blastExe = '/usr/bin/blastn';
        $queryFile = '/tmp/jobs/job_123/query.fasta';
        $resultsFile = '/tmp/jobs/job_123/results.html';
        $database = '/data/blastdb/testdb';
        $evalue = '1e-5';
        $maxHits = 10;
        
        $cmd = escapeshellarg($blastExe) . 
               ' -query ' . escapeshellarg($queryFile) . 
               ' -html -out ' . escapeshellarg($resultsFile) .
               ' -db ' . escapeshellarg($database) .
               ' -evalue ' . escapeshellarg($evalue) .
               ' -max_target_seqs ' . escapeshellarg($maxHits);
        
        $this->assertStringContainsString('blastn', $cmd);
        $this->assertStringContainsString('-query', $cmd);
        $this->assertStringContainsString('-html', $cmd);
        $this->assertStringContainsString('-db', $cmd);
        $this->assertStringContainsString('-evalue', $cmd);
        $this->assertStringContainsString('-max_target_seqs', $cmd);
    }
    
    /**
     * Test command escaping prevents injection
     */
    public function testCommandInjectionPrevention() {
        $maliciousInput = "'; rm -rf /; echo '";
        $escaped = escapeshellarg($maliciousInput);
        
        $this->assertStringNotContainsString('rm -rf', $escaped);
        $this->assertStringStartsWith("'", $escaped);
        $this->assertStringEndsWith("'", $escaped);
    }
    
    /**
     * Test status parsing
     */
    public function testStatusParsing() {
        $statuses = [
            'pending' => 'pending',
            'running' => 'running',
            'completed' => 'completed',
            'failed' => 'failed',
            'PENDING' => 'pending',
            '  running  ' => 'running'
        ];
        
        foreach ($statuses as $input => $expected) {
            $normalized = strtolower(trim($input));
            $this->assertEquals($expected, $normalized);
        }
    }
    
    /**
     * Test date formatting
     */
    public function testDateFormatting() {
        $date = date('Y-m-d H:i:s');
        
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date);
    }
    
    /**
     * Test default parameter handling
     */
    public function testDefaultParameters() {
        $params = [
            'jobId' => 'job_123',
            'blastexe' => 'blastn',
            'query' => 'ATCG'
            // database, evalue, maxHits not provided
        ];
        
        $database = isset($params['database']) ? $params['database'] : null;
        $evalue = isset($params['evalue']) ? $params['evalue'] : '1e-5';
        $maxHits = isset($params['maxHits']) ? intval($params['maxHits']) : 10;
        
        $this->assertNull($database);
        $this->assertEquals('1e-5', $evalue);
        $this->assertEquals(10, $maxHits);
    }
    
    /**
     * Test JSON encoding of job data
     */
    public function testJsonEncoding() {
        $jobData = [
            'jobId' => 'job_123',
            'status' => 'pending',
            'created' => '2024-01-01 12:00:00'
        ];
        
        $json = json_encode($jobData, JSON_PRETTY_PRINT);
        
        $this->assertIsString($json);
        $this->assertStringContainsString('job_123', $json);
        $this->assertStringContainsString('pending', $json);
        
        // Verify it can be decoded back
        $decoded = json_decode($json, true);
        $this->assertEquals($jobData, $decoded);
    }
    
    /**
     * Test file name generation
     */
    public function testFileNameGeneration() {
        $jobId = 'job_1234567890_abcd1234';
        
        $files = [
            'query.json',
            'query.fasta',
            'results.html',
            'status.txt',
            'error.log',
            'blast.pid'
        ];
        
        foreach ($files as $file) {
            $fullPath = "/tmp/jobs/$jobId/$file";
            $this->assertStringContainsString($jobId, $fullPath);
            $this->assertStringEndsWith($file, $fullPath);
        }
    }
}
