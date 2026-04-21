"use strict";

let LMSClasses = {
	Eventbus: './ctrl/Eventbus.js',
	SRB2HTTPTableComponent: './cmp/SRB2HTTPTableComponent.js',
	//SRB2KartTableComponent: './cmp/SRB2KartTableComponent.js',
	NetgameModel: './mdl/NetgameModel.js', // Deprecated
	//SRB2HTTPNetgameModel: './mdl/SRB2HTTPNetgameModel.js',
	//SRB2KartNetgameModel: './mdl/SRB2KartNetgameModel.js',
	RequestController: './ctrl/RequestController.js',
};


if(LMSBrowser == undefined){
	var LMSBrowser = { };
	// Class loader
	for( const component in LMSClasses ){
	  import(LMSClasses[component]).then( function(module){
		  LMSClasses[component] = module.default;
		  if(LMSClasses[component].prototype.init != undefined){ LMSClasses[component].prototype.init(); }
	  });
	};
}

window.onload = function(){
	// Global objects used to be defined here,
	// but we're using static classes now. Example:
	//
	// LMSBrowser.db = new LMSClasses.NetgameModel();
	
	console.log('LMSBrowser initialized.');
}

