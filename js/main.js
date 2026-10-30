define([
    'dojo/_base/declare',
    'dojo/_base/lang',
    'dojo/Deferred',
    'dojo/dom-construct',
    'dojo/query',
    'dijit/Menu',
    'dijit/MenuItem',
    'dijit/form/Button',
    'JBrowse/Plugin'
],
function(
    declare,
    lang,
    Deferred,
    domConstruct,
    query,
    Menu,
    MenuItem,
    Button,
    JBrowsePlugin
) {
    return declare( JBrowsePlugin,
    {
        sendTo: function(dnaRegion) {

            // dna comes from selected feature
            if (dnaRegion) {
                return sendIt(dnaRegion);
            }

            JBrowse.ggblast_plugin.processInput((postData) => {
                
                if (postData.err) {
                    alert(postData.err);
                    return;
                }

                sendIt(postData);
            });

            function sendIt(data) {
                console.log("Sending to BLAST",data);

                // insert database name
                let r = data.region.split('\n');
                let db = JBrowse.config.dataRoot.split('/');
                db = db[db.length-1];
                r[0] += " GrainGenes="+db;
                data.region = r.join('\n');

                // Check if using PHP BLAST service
                if (JBrowse.ggblast_plugin.blastService == true) {
                    // Submit job via PHP API

                    if (!JBrowse.config.blastDatabase) {
                        alert("blastDatabase not defined in trackList.json, cannot send to BLAST");
                        return;
                    }
                    // Prepare POST data for submit_job.php
                    const formData = new FormData();
                    formData.append('blastexe', 'blastn'); // Default to blastn, could be made configurable
                    formData.append('query', data.region);
                    formData.append('database', JBrowse.config.blastDatabase);
                    
                    // Optional parameters from config
                    if (JBrowse.config.blastEvalue) {
                        formData.append('evalue', JBrowse.config.blastEvalue);
                    }
                    if (JBrowse.config.blastMaxHits) {
                        formData.append('maxHits', JBrowse.config.blastMaxHits);
                    }

                    console.log("Submitting BLAST job via PHP API to database:", JBrowse.config.blastDatabase);

                    // Submit the job
                    fetch('plugins/GGBlastPlugin/blast/submit_job.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        console.log("Full API response:", result);
                        if (result.success) {
                            console.log("BLAST job submitted:", result.jobId);
                            console.log("Full BLAST command:", result.command);
                            
                            // Open results page in new tab to check status and view results
                            const resultsUrl = `plugins/GGBlastPlugin/blast/results.php?jobId=${result.jobId}`;
                            window.open(resultsUrl, '_blank');
                        } else {
                            alert("BLAST job submission failed:\n" + (result.error || "Unknown error"));
                            console.error("BLAST submission error:", result);
                        }
                    })
                    .catch(error => {
                        alert("Failed to submit BLAST job:\n" + error.message);
                        console.error("BLAST submission error:", error);
                    });
                }
                else {
                    // Use POST /query_transfer if available, otherwise fall back to localStorage.
                    // Sanity check: POST with no body — route returns {"ok":true} if it exists.
                    const blastAppBase = JBrowse.ggblast_plugin.blastApp.replace(/\/?$/, '/');
                    const queryTransferUrl = blastAppBase + 'query_transfer';

                    fetch(queryTransferUrl, { method: 'POST' })
                        .then(response => {
                            if (!response.ok) throw new Error('query_transfer not available');
                            return response.json();
                        })
                        .then(sanity => {
                            if (!sanity.ok) throw new Error('query_transfer sanity check failed');

                            // Route exists — POST the query and database data
                            return fetch(queryTransferUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    database: JBrowse.config.blastDatabase,
                                    query: data.region
                                })
                            });
                        })
                        .then(response => response.json())
                        .then(result => {
                            // Check for database error message returned by the server
                            if (result.missingDbs) {
                                alert("Missing BLAST database(s): " + result.missingDbs);
                                return;
                            }
                            // Open BLAST page with xid parameter
                            if (!JBrowse.config.blastDatabase) {
                                console.warn("GGBlastPlugin: blastDatabase is missing or undefined");
                            }
                            blastUrl = blastAppBase + '?xid=' + result.xid;
                            console.log("Opening BLAST:", blastUrl);
                            window.open(blastUrl, '_newtab');
                        })
                        .catch((error) => {
                            // Fallback: original localStorage behavior
                            console.warn("query_transfer unavailable, falling back to localStorage:", error);
                            localStorage.setItem('blastDNA', data.region);
                            if (!JBrowse.config.blastDatabase) {
                                console.warn("GGBlastPlugin: blastDatabase is missing or undefined");
                            }
                            console.log("select BLAST database", JBrowse.config.blastDatabase);
                            localStorage.setItem('blastDatabaseSelect', JBrowse.config.blastDatabase);
                            blastUrl = JBrowse.ggblast_plugin.blastApp;
                            console.log("Legacy - Opening BLAST:", blastUrl);
                            window.open(blastUrl, '_newtab');
                        });
                }
            }
        },

        constructor: function( args ) {
            let thisB = this;
            let browser = this.browser;

            console.log("plugin: GGBlastPlugin");
            console.log("domain:", window.location.protocol + "//" + window.location.hostname);

            browser.ggblast_plugin = {
                asset: null,
                browser: browser,
                panelDelayTimer: null,
                bpSizeLimit: 20000, // Default value, can be overridden by config.json or trackList.json
                blastService: null, // Can be set in config.json or trackList.json
                blastApp: 'https://graingenes.org/blast/', // Default URL, can be overridden by config.json or trackList.json
                sendTo: thisB.sendTo,
                processInput: processInput,
    
                // check if bpSize > bpSizeLimit, if bpSizeLimit is defined
                isOversized(bpSize) {
                    console.log('checking size',bpSize,'/',bpSizeLimit);
                    let bpSizeLimit = JBrowse.ggblast_plugin.bpSizeLimit;
    
                    if (bpSizeLimit && bpSize > bpSizeLimit) {
                        // oversize message
                        alert("The selected query size is "+bpSize+" bp.  Query is limited to "+bpSizeLimit+" bp.  bpSizeLimit can be set in config.json or trackList.json.");
                        return true;
                    }
                    else return false;
                }
            };

            // Deferred to signal when config.json (plus any trackList.json overrides) is loaded
            let configLoaded = new Deferred();
            browser.ggblast_plugin._configLoaded = configLoaded;

            // Load config.json and merge settings, then allow trackList.json to override
            fetch('plugins/GGBlastPlugin/config.json')
                .then(response => response.json())
                .then(config => {
                    // Merge all config.json properties into ggblast_plugin
                    Object.keys(config).forEach(key => {
                        browser.ggblast_plugin[key] = config[key];
                    });
                    console.log('Loaded config from config.json:', config);
                    
                    // Allow trackList.json settings to override config.json
                    if (browser.config.bpSizeLimit !== undefined) {
                        browser.ggblast_plugin.bpSizeLimit = browser.config.bpSizeLimit;
                        console.log('Overriding bpSizeLimit from trackList.json:', browser.config.bpSizeLimit);
                    }
                    if (browser.config.blastService !== undefined) {
                        browser.ggblast_plugin.blastService = browser.config.blastService;
                        console.log('Overriding blastService from trackList.json:', browser.config.blastService);
                    }
                    if (browser.config.blastApp !== undefined) {
                        browser.ggblast_plugin.blastApp = browser.config.blastApp;
                        console.log('Overriding blastApp from trackList.json:', browser.config.blastApp);
                    }
                })
                .catch(error => {
                    console.warn('Could not load config.json, using defaults:', error);
                    
                    // Still allow trackList.json settings even if config.json fails
                    if (browser.config.bpSizeLimit !== undefined) {
                        browser.ggblast_plugin.bpSizeLimit = browser.config.bpSizeLimit;
                    }
                    if (browser.config.blastService !== undefined) {
                        browser.ggblast_plugin.blastService = browser.config.blastService;
                    }
                    if (browser.config.blastApp !== undefined) {
                        browser.ggblast_plugin.blastApp = browser.config.blastApp;
                    }
                })
                .finally(() => {
                    configLoaded.resolve();
                });

            // override BlockBased - for right click highlighted region
            require(["dojo/_base/lang", "JBrowse/View/Track/BlockBased"], function(lang, BlockBased){
                lang.extend(BlockBased, {
                    postRenderHighlight: thisB.BlockBased_postRenderHighlight
                });
            });
            // override FASTA - for inserting BLAST button in Feature Details DNA box
            require(["dojo/_base/lang", "JBrowse/View/FASTA"], function(lang, FASTA){
                lang.extend(FASTA, {
                    addButtons: thisB.FASTA_addButtons
                });
            });
            // override Browser
            require(["dojo/_base/lang", "JBrowse/Browser"], function(lang, Browser){
                lang.extend(Browser, {
					// handle highlight off 
                    clearHighlight: function() {
                        if( this._highlight ) {
                            $("[widgetid='jblast-toolbtn']").hide();
                            delete this._highlight;
                            this.publish( '/jbrowse/v1/n/globalHighlightChanged', [] );
                        }
                    }
                });
            });
            

            // setup navbar blast button
            var navBox = dojo.byId("navbox");
            thisB.browser.ggblast_plugin.blastButton = new Button(
            {
                title: "BLAST highlighted region",
                id: "jblast-toolbtn",
				label: "BLAST",
                onClick: dojo.hitch( thisB, function(event) {
                    thisB.sendTo();
                    dojo.stopEvent(event);
                })
            }, dojo.create('button',{},navBox));   //thisB.browser.navBox));

            // setup right click menu for highlight region - for arbitrary region selection
            thisB.rightClickMenuInit();
            
            // insert dropdown menu
            browser.afterMilestone( 'initView', function() {    
                let menuName = "blast"; 
                browser.renderGlobalMenu( menuName,'AnalyzeTools', browser.menuBar );
                
                initMenu(menuName);
                
                // Add Jobs menu item only after config loads and blastService is configured/enabled
                configLoaded.then(function() {
                    let service = browser.ggblast_plugin.blastService;
                    if (service === 'php' || service === true) {
                        browser.addGlobalMenuItem( menuName, new MenuItem({
                            id: 'menubar_blast_jobs',
                            label: 'Jobs',
                            iconClass: 'dijitIconFolderOpen',
                            onClick: function() {
                                window.open('plugins/GGBlastPlugin/blast/jobs.php', '_blank');
                            }
                        }));
                    }
                });
            });

            function initMenu(menuName) {
                browser.addGlobalMenuItem( menuName, new MenuItem({
                    id: 'menubar_' + menuName + '_submit_demo',
                    label: 'BLAST highlighted region',
                    iconClass: 'dijitIconFilter',
                    onClick: function() {
    
                        if (!browser._highlight) {
                            alert("no highlight region");
                            return;
                        }
    
                        let bpSize = browser._highlight.end - browser._highlight.start;
                        if (browser.ggblast_plugin.isOversized(bpSize))  return;
    
                        thisB.sendTo();
                        return;

                    }
                }));
            }

            // after Submit button is pressed, this processes input from the dialog prior to submitting the job.
            function processInput(cb) {
                if (!browser._highlight) {
                    return cb({
                        err: "_no highlight region"
                    });
                }

                // check if bpSize is oversized
                let bpSize = browser._highlight.end - browser._highlight.start;
                if (browser.ggblast_plugin.isOversized(bpSize))  return {err: "oversized"};
    
                // get parameter list
                let params = {}; 
                $( ".s-params .s-data" ).each(function( i ) {
                    params[$(this).attr('name')] = $( this ).val();
                });            
                
                // get the highlighted region data
                browser.getStore('refseqs', dojo.hitch(this,function( refSeqStore ) {
                    if( refSeqStore ) {
                        var hilite = browser._highlight;
                        refSeqStore.getReferenceSequence(
                            hilite,
                            dojo.hitch( this, function( seq ) {
                                let bpSize = hilite.end-hilite.start;
                                //console.log('startBlast() found sequence',hilite,bpSize);
                                require(["JBrowse/View/FASTA"], function(FASTA){
                                    var fasta = new FASTA();
                                    var fastaData = fasta.renderText(hilite,seq);
                                    cb({
                                        region:fastaData,
                                        bpSize:bpSize,
                                        params:params
                                    });
                                });                                
                            })
                        );
                    }
                }));             
            }

        },
        /*
         *
         */
        rightClickMenuInit: function(highlight) {
            //var thisB = this;
            var browser = this.browser;
            var handlers = {
                // handler for clicks on task context menu items
                onTaskItemClick: function(event) {
                    // get sequence store and ac
                    //thisB.startBlast();
                    JBrowse.ggblast_plugin.sendTo();
                }
            };
            // create task menu as context menu for task nodes.
            
            var menu = new Menu({
                    id: "jblastRCMenu"
            });
            menu.addChild(new MenuItem({
                    id: "jblast-region",
                    label: "BLAST highlighted region...",
                    onClick: lang.hitch(handlers, "onTaskItemClick")
            }) );
            menu.startup();
            menu.note = "right-click hilite menu";
    
            browser.jblastHiliteMenu = menu;
        },
        /**
         * called when highlight region is created
         * @param {type} node - DOM Node of highlight region (yellow region)
         * @returns nothing significant
         */
        BlockBased_postRenderHighlight: function(node) {
            //console.log('postRenderHighlight');
            
            // add hilight menu to node
            if (typeof JBrowse.jblastHiliteMenu !== 'undefined') {
                JBrowse.jblastHiliteMenu.bindDomNode(node);
                $("[widgetid='jblast-toolbtn']").show();

                // flash the BLAST button
                $("[widgetid='jblast-toolbtn']")
                .fadeIn(100).fadeOut(100).fadeIn(100)
                .fadeOut(100).fadeIn(100)
                .fadeOut(100).fadeIn(100)
                .fadeOut(100).fadeIn(100);            

            }
        },
        // adds Blast button in feature DNA in details dialogbox
        FASTA_addButtons: function (region,seq, toolbar) {
            let text = this.renderText( region, seq );
            let bpSize = region.end-region.start;
            
            toolbar.addChild( new Button({ 
                iconClass: 'dijitIconFunction',
                label: 'BLAST',
                title: 'BLAST this feature',
                disabled: false, //$('.save-generated-files'),
                onClick: function() {
                    let btn = $(".dijitButton[widgetid='"+this.id+"']").parent().parent();
                    let rdata = $('textarea.fasta',btn).text();
                    let data = {
                        bpSize: bpSize,
                        region: rdata,
                    }
                    JBrowse.ggblast_plugin.sendTo(data);
                }
            }));
        },
    
    });
});