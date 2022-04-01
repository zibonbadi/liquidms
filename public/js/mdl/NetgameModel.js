export default class NetgameModel{
	constructor(){
		this.servers = [];
	}

	async insert(data = []){
		let subjects = [];
		let toRefresh = [];
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
		for(let sv in subjects){
			let valid = true;
			for(let field in subjects[sv]){
				if(typeof(subjects[sv][field]) == "undefined"){valid = false;}
			}
			if(!valid){
				continue;
			}

			let insert = {};
			insert.hostname = subjects[sv].hostname;
			insert.port = subjects[sv].port;
			try{
				insert.name = decodeURIComponent(servers[sv].name);
			}catch (error) {
				insert.name = subjects[sv].name;
			}
			insert.version = subjects[sv].version;
			insert.roomname = subjects[sv].roomname;
			insert.origin = subjects[sv].origin;

			// this.servers for all subjects, toRefresh for update culling
			this.servers[insert.hostname] = insert;
			toRefresh[insert.hostname] = insert;
					
		}
		ServerBrowser.eventbus.send("refresh", toRefresh);
		//ServerBrowser.eventbus.send("refresh");
	}

}

