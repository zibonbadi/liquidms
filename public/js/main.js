"use strict";

/*
fetch('/liquidms/snitch')
	.then((res) => res.text() )
	.then( (text) => {
		console.log(CSVToArray(text));
	}).catch( (error) => { console.log("Fetch error: ", error); });
*/

let SBModules = {
	Eventbus: './ctrl/eventbus.js',
	NetgameComponent: './cmp/netgame.js',
	NetgameController: './ctrl/netgame.js',
	NetgameListComponent: './cmp/netgamelist.js',
	NetgameModel: './mdl/netgame.js',
	RequestController: './ctrl/request.js',
};


if(ServerBrowser == undefined){
	var ServerBrowser = { };
	// Class loader
	for( const component in SBModules ){
	  import(SBModules[component]).then( function(module){
		  SBModules[component] = module.default;
	  });
	};
}

window.onload = function(){
	ServerBrowser.req = new SBModules.RequestController();
	ServerBrowser.eventbus = new SBModules.Eventbus();
	ServerBrowser.db = new SBModules.NetgameModel();
	ServerBrowser.netgamecon = new SBModules.NetgameController();

	/*
	ServerBrowser.req.get('/liquidms/snitch')
		.then( (response) => {
			console.log(response);
		})
		.catch( (error) => {
			console.error("Initial fetch failed: ", error);
		});
		*/

	console.log('ServerBrowser initialized.');
}

