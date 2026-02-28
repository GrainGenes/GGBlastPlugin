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

            JBrowse.jbconnect.processInput((postData) => {
                
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

                if (!JBrowse.config.blastDatabase) {
                    alert("blastDatabase not defined in trackList.json, cannot send to BLAST");
                    return;
                }

                // Check if using PHP BLAST service
                if (JBrowse.config.blastService === "php") {
                    // Submit job via PHP API

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
                    fetch('plugins/SequenceLinkOut/blast/submit_job.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            console.log("BLAST job submitted:", result.jobId);
                            
                            // Open get_job page in new tab to check status and view results
                            const getJobUrl = `plugins/SequenceLinkOut/blast/get_job.php?jobId=${result.jobId}`;
                            window.open(getJobUrl, '_blank');
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
                    // Original behavior: store in localStorage and open BLAST page
                    localStorage.setItem('blastDNA',data.region);
                
                    console.log("select BLAST database",JBrowse.config.blastDatabase);
                    localStorage.setItem('blastDatabaseSelect',JBrowse.config.blastDatabase);

                    // send to BLAST page
                    window.open('/blast','_newtab');
                }
            }
        },

        constructor: function( args ) {
            let thisB = this;
            let browser = this.browser;

            console.log("plugin: SequenceLinkOut");

            browser.jbconnect = {
                asset: null,
                browser: browser,
                panelDelayTimer: null,
                bpSizeLimit: browser.config.bpSizeLimit || 20000,
                analyzeMenus: {},
                sendTo: thisB.sendTo,
                processInput: processInput,
    
                // check if bpSize > bpSizeLimit, if bpSizeLimit is defined
                isOversized(bpSize) {
                    console.log('checking size',bpSize,'/',bpSizeLimit);
                    let bpSizeLimit = JBrowse.jbconnect.bpSizeLimit;
    
                    if (bpSizeLimit && bpSize > bpSizeLimit) {
                        // oversize message
                        alert("The selected query size is "+bpSize+" bp.  Query is limited to "+bpSizeLimit+" bp.  bpSizeLimit can be set in trackList.json.");
                        return true;
                    }
                    else return false;
                }
            };

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
            thisB.browser.jbconnect.blastButton = new Button(
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
            
            // setup content of submit dialog box
            function dialogContent(container) {
            }

            // BLAST menu structure
            browser.jbconnect.analyzeMenus.demo = {
                title: 'Submit to ggBlast',
                module: 'demo',
                init:initMenu,
                contents:dialogContent,
                process:processInput
            };
            
            // insert dropdown menu
            browser.afterMilestone( 'initView', function() {    
                let menuName = "analyze"; 
                browser.renderGlobalMenu( menuName,'AnalyzeTools', browser.menuBar );
                
                thisB.initAnalyzeMenu();
            });

            // initMenu sets up Analyze Menu item(s)
            
            function initMenu(menuName) {
                browser.addGlobalMenuItem( menuName, new MenuItem({
                    id: 'menubar_submit_demo',
                    label: 'BLAST highlighted region',
                    iconClass: 'dijitIconFilter',
                    onClick: function() {
    
                        if (!browser._highlight) {
                            alert("no highlight region");
                            return;
                        }
    
                        let bpSize = browser._highlight.end - browser._highlight.start;
                        if (browser.jbconnect.isOversized(bpSize))  return;
    
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
                if (browser.jbconnect.isOversized(bpSize))  return {err: "oversized"};
    
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
        initAnalyzeMenu() {
            let thisB = this;
            let browser = this.browser;
            this.plugin = this;
            let menuName = "analyze"; 
            require([
                'dojo/dom-construct',
                'dijit/MenuItem',
                'dijit/Dialog',
                'dijit/form/Button'
            ], function(dom,dijitMenuItem,Dialog,dButton,queryDialog){
                
                let analyzeMenus = browser.jbconnect.analyzeMenus;
    
                for(let i in analyzeMenus) {
                    if (analyzeMenus[i].queryDialog) 
                        analyzeMenus[i].init(menuName,analyzeMenus[i].queryDialog)
                    else
                        analyzeMenus[i].init(menuName,queryDialog);
                }
                browser.renderGlobalMenu( menuName,'AnalyzeTools', browser.menuBar );
                
                // reorder the menubar
                $("[widgetid*='dropdownbutton_analyze']").insertBefore("[widgetid*='dropdownbutton_help']");
                $("[widgetid*='dropdownbutton_analyze'] span.dijitButtonNode").html(" BLAST");
    
            });
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
                    JBrowse.jbconnect.sendTo();
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
                    JBrowse.jbconnect.sendTo(data);
                }
            }));
        },
    
    });
});