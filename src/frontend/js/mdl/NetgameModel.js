import EventBus from '../ctrl/Eventbus.js';

export default class NetgameModel{
	static servers = [];
	constructor(){
	}

	static async insert(data = []){
		async function sleep(ms){ return new Promise((res) => setTimeout(res, ms)); };

		let subjects = [];
		let toRefresh = [];
		console.debug("NetgameModel: Bulk update...")
		switch(typeof(data)){
		case "array":
		case "object":{
			// Janky JavaScript, bleh
			if(Array.isArray(data)){
				subjects = data;
			}else{
				subjects = [data];
			}
			break;
		}
		default:{
			throw "Invalid data provided to NetgameModel!";
			break;
		}
		};
		for(let sv of subjects){
			let valid = true;
			for(let field in sv){
				if(typeof(sv[field]) == "undefined"){valid = false;}
			}
			if(!valid){
				continue;
			}

			let insert = {};
			insert.hostname = sv.hostname;
			insert.port = sv.port;
			insert.name = sv.name;
			insert.version = sv.version;
			insert.roomname = sv.roomname;
			insert.origin = sv.origin;
			//insert.updated_at = new Date().toLocaleString();

			// this.servers for all , toRefresh for update culling
			let hostkey = `${insert.hostname}:${insert.port}`;
			this.servers[hostkey] = insert;
			toRefresh[hostkey] = insert;
					
			// Offset request flood
			let passthru = [];
			passthru[hostkey] = insert;
			EventBus.send("refresh", passthru);
			await sleep(25);
		}
		//EventBus.send("refresh", toRefresh);
	};

	static async populateOne(hostkey, data = {}){
		if(this.servers[hostkey] == undefined){this.servers[hostkey] = {}; }
		this.servers[hostkey]._meta = data;
		let shim = {};
		shim[hostkey] = this.servers[hostkey];
		EventBus.send("refresh", shim);
	};

	static _match_recurse(obj, pattern){
		// Iteratively try to match data
		console.log("Searching for ", pattern, " in ", obj);
		for (var prop in obj) {
			if (Object.prototype.hasOwnProperty.call(obj, prop)) {
				// Now we're safe from inheritance -> actually check the fields
				if(typeof obj[prop] == "object" &&
					this._match_recurse(obj[prop], pattern)){
					return true;
				}
				else if(typeof obj[prop] == "string" &&
					obj[prop].match(pattern)){
						return true;
					}
			}
		}

		return false; // Failsafe
	}

	static match(hostkey, pattern){
		return this._match_recurse(this.servers[hostkey], pattern);
	}
}

