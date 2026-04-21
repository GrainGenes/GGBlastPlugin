#!/bin/bash
# BLAST wrapper script

# Run BLAST and capture exit code
'/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/ncbi-blast-2.17.0+/bin/blastn' -query '/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/demo-jobs/job_1776803955_b073796a/query.fasta' -html -out '/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/demo-jobs/job_1776803955_b073796a/results.html' -db '/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/demo-blastdb/S_urartu' -evalue '1e-5' -max_target_seqs '10'
BLAST_EXIT=$?

# Update status based on result
if [ $BLAST_EXIT -eq 0 ] && [ -s "/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/demo-jobs/job_1776803955_b073796a/results.html" ]; then
    echo "completed" > "/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/demo-jobs/job_1776803955_b073796a/status.txt"
    # Update query.json with completion status
    php -r "
        \$file = '/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/demo-jobs/job_1776803955_b073796a/query.json';
        \$data = json_decode(file_get_contents(\$file), true);
        \$data['status'] = 'completed';
        \$data['completed'] = date('Y-m-d H:i:s');
        file_put_contents(\$file, json_encode(\$data, JSON_PRETTY_PRINT));
    "
else
    echo "failed" > "/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/demo-jobs/job_1776803955_b073796a/status.txt"
    # Update query.json with failure status
    php -r "
        \$file = '/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/demo-jobs/job_1776803955_b073796a/query.json';
        \$data = json_decode(file_get_contents(\$file), true);
        \$data['status'] = 'failed';
        \$data['error'] = 'BLAST execution failed with exit code: $BLAST_EXIT';
        if (file_exists('/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/demo-jobs/job_1776803955_b073796a/error.log')) {
            \$data['errorDetails'] = file_get_contents('/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/demo-jobs/job_1776803955_b073796a/error.log');
        }
        file_put_contents(\$file, json_encode(\$data, JSON_PRETTY_PRINT));
    "
fi

# Clean up PID file
rm -f "/data/ggjbrowse/jb-versions/jb11612/plugins/GGBlastPlugin/demo-jobs/job_1776803955_b073796a/blast.pid"