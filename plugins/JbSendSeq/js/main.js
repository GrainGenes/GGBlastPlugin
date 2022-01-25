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
        sendTo: function(region) {

            JBrowse.jbconnect.processInput((postData) => {
                
                console.log("Sending to BLAST",postData);
                
                if (postData.err) {
                    alert(postData.err);
                    return;
                }

                localStorage.setItem('blastDNA',postData.region);
                window.open('https://graingenes.org/blast','_newtab');
            });
        },

        constructor: function( args ) {
            let thisB = this;
            let browser = this.browser;

            console.log("plugin: JbSendSeq");

            browser.jbconnect = {
                asset: null,
                browser: browser,
                panelDelayTimer: null,
                bpSizeLimit: browser.config.bpSizeLimit || 20000,
                //countSequence: thisB.countSequence,
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
                            //domStyle.set(thisB.browser.jblast.blastButton, 'display', 'none');  // don't work, why?
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
					//thisB.startBlast();
                    thisB.sendTo();
                    dojo.stopEvent(event);
                })
            }, dojo.create('button',{},navBox));   //thisB.browser.navBox));

            // setup right click menu for highlight region - for arbitrary region selection
            thisB.jblastRightClickMenuInit();

            // analyze menu structure
            
            browser.jbconnect.analyzeMenus.demo = {
                title: 'Submit to ggBlast',
                //title: 'Demo Analysis',
                module: 'demo',
                init:initMenu,
                contents:dialogContent,
                process:processInput
            };
            
            // insert dropdown menu
            browser.afterMilestone( 'initView', function() {    
                console.log(">>>>> Setup Menu");

                let menuName = "analyze"; 
                browser.renderGlobalMenu( menuName,'AnalyzeTools', browser.menuBar );
                
                thisB.initAnalyzeMenu();
            });

            // initMenu sets up Analyze Menu item(s)
            
            function initMenu(menuName,queryDialog,container) {
                browser.addGlobalMenuItem( menuName, new MenuItem({
                    id: 'menubar_submit_demo',
                    label: 'BLAST highlighted region',
                    //iconClass: 'dijitIconFilter',
                    onClick: function() {
    
                        if (!browser._highlight) {
                            alert("no highlight region");
                            return;
                        }
    
                        let bpSize = browser._highlight.end - browser._highlight.start;
                        if (browser.jbconnect.isOversized(bpSize))  return;
    
                        thisB.sendTo();
                        return;

                        let btnState = $("[widgetid*='highlight-btn'] > input").attr('aria-checked');
                        console.log("btnState",btnState,typeof btnState);
                        if (btnState==='mixed') {
                            // launch blast dialog
                            console.log("launch demo dialog");
                            startSampleDialog();
    
                        }
                        if (btnState==='false' || btnState==='true') {
                            // false - highlight button hasn't been pressed
                            // true - highlight button has been pressed but region not selected yet.
    
                            let txt = "";
                            txt += 'This feature allows you to select an arbitrary region to submit for analysis using the highlight region feature of JBrowse. <p/>';
                            
                            if (btnState==='false') {
                                txt += 'To begin, click the highlight button <img src="plugins/JBAnalyze/img/hilite_unselected.PNG" height="22px" /> on the toolbar to begin the highlight mode. ';
                            }
                            if (btnState==='true') {
                                txt += 'You have selected the highlight button, which now appears yellow <img src="plugins/JBAnalyze/img/hilite_selected.PNG" height="22px" />. ';
                            }
                            txt += 'Highlight the region by clicking the start coordinate in the track area of the genome browser, ';
                            txt += 'holding down and dragging to the end coordinate and releasing. ';
    
                            // show highlight instruct box
                            var confirmBox = new Dialog({ title: 'Highlight region to submit for analysis' });
                            dojo.create('div', {
                                id: 'confirm-btn',
                                style: "width: 700px;padding:15px",
                                innerHTML: txt
            
                            }, confirmBox.containerNode );
                            new Button({
                                id: 'ok-btn1',
                                label: 'Ok',
                                //iconClass: 'dijitIconDelete',
                                onClick: function() {
                                    confirmBox.destroyRecursive();
                                    //confirmCleanBox.hide();
                                }
                            })
                            .placeAt( confirmBox.containerNode );
    
                            confirmBox.show();
    
                        }
                    }
                }));
                function startSampleDialog() {
                                        
                        var dialog = new queryDialog({
                            browser:thisB.browser,
                            plugin:thisB.plugin,
                            //workflows:thisb.workflows
                        });
                        dialog.analyzeMenu = browser.jbconnect.analyzeMenus.demo; 
                        dialog.show(function(x) {});
                } 
                         
            }
            


            // setup content of submit dialog box
            
            function dialogContent(container) {
                //Render textarea box
    
                // var searchBoxDiv = dom.create('div', {
                //     className: "section",
                //     innerHTML:
                //         '<div class="s-params">'
                //         +'    Add CUSTOM_DATA= attribute to all features in the demo result.<br/>'
                //         +'    <input class="s-data" type="text" name="CUSTOM_DATA">'
                //         +'</div>'
                // }, container );
    
                // // setup default values for fields
                // setTimeout(function() {
                //     $('.s-data[name=CUSTOM_DATA]').val('hello world');
                // },200);
            }
            
            // after Submit button is pressed, this processes input from the dialog prior to submitting the job.
            function processInput(cb) {
                console.log ('JBCdemo processInput',browser);
    
                // check if bpSize is oversized
                let bpSize = browser._highlight.end - browser._highlight.start;
                if (browser.jbconnect.isOversized(bpSize))  return {err: "oversized"};
    
                // get parameter list
                let params = {}; 
                $( ".s-params .s-data" ).each(function( i ) {
                    console.log( $(this).attr('name')+ ": " + $( this ).val() );
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
                'dijit/form/Button',
                'plugins/JbSendSeq/js/queryDialog'
            ], function(dom,dijitMenuItem,Dialog,dButton,queryDialog){
                
                let analyzeMenus = browser.jbconnect.analyzeMenus;
    
                console.log("JBAnalyze toolmenu init",analyzeMenus);//Object.keys(analyzeMenus).length);
                

                for(let i in analyzeMenus) {
                    console.log("Analyze menu",i);
                    if (analyzeMenus[i].queryDialog) 
                        analyzeMenus[i].init(menuName,analyzeMenus[i].queryDialog)
                    else
                        analyzeMenus[i].init(menuName,queryDialog);
                }
                browser.renderGlobalMenu( menuName,'AnalyzeTools', browser.menuBar );
                
                // reorder the menubar
                $("[widgetid*='dropdownbutton_analyze']").insertBefore("[widgetid*='dropdownbutton_help']");
                //$("[widgetid*='dropdownbutton_analyze'] span.dijitButtonNode").html(" Analyze");
                $("[widgetid*='dropdownbutton_analyze'] span.dijitButtonNode").html(" BLAST");
    
            });
        },
        /*
         *
         */
        jblastRightClickMenuInit: function(highlight) {
            //console.log("jblastRightClickMenuInit");
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
            console.log('postRenderHighlight');
            
            // add hilight menu to node
            if (typeof JBrowse.jblastHiliteMenu !== 'undefined') {
                JBrowse.jblastHiliteMenu.bindDomNode(node);
                $("[widgetid='jblast-toolbtn']").show();
                //domStyle.set(thisB.browser.jblast.blastButton, 'display', 'inline'); // dont work, why??

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
            //console.log("addButtons region, size",region,region.end-region.start,text);
            //let thisB = this;
            let bpSize = region.end-region.start;
            
            toolbar.addChild( new Button({ 
                iconClass: 'dijitIconFunction',
                label: 'BLAST',
                title: 'BLAST this feature',
                disabled: false, //$('.save-generated-files'),
                onClick: function() {
                    // check if query size too big
                    //if (JBrowse.jblast.isOversized(bpSize)) return;
                    //JBrowse.jblastDialog(text,bpSize);
                    JBrowse.jbconnect.sendTo();
                }
            }));
        },
    
    });
});