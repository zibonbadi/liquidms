"use strict";

/*
fetch('/liquidms/snitch')
	.then((res) => res.text() )
	.then( (text) => {
		console.log(CSVToArray(text));
	}).catch( (error) => { console.log("Fetch error: ", error); });
*/

let SBClasses = {
	Eventbus: './ctrl/Eventbus.js',
	RequestController: './ctrl/RequestController.js',
	NetgameModel: './mdl/NetgameModel.js',
	NetgameComponent: './cmp/NetgameComponent.js',
	NetgameController: './ctrl/NetgameController.js',
	NetgameListComponent: './cmp/NetgameListComponent.js',
};


if(ServerBrowser == undefined){
	var ServerBrowser = { };
	// Class loader
	for( const component in SBClasses ){
	  import(SBClasses[component]).then( function(module){
		  SBClasses[component] = module.default;
		  //console.log(component,SBClasses[component].prototype.init);
		  if(SBClasses[component].prototype.init != undefined){ SBClasses[component].prototype.init(); }
	  });
	};
}

window.onload = function(){
	ServerBrowser.req = new SBClasses.RequestController();
	ServerBrowser.eventbus = new SBClasses.Eventbus();
	ServerBrowser.db = new SBClasses.NetgameModel();
	ServerBrowser.netgamecon = new SBClasses.NetgameController();

	/*
	customElements.define('sb-netgamelist', SBClasses.NetgameListComponent);
	customElements.define('sb-netgame', SBClasses.NetgameComponent);
	*/

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

