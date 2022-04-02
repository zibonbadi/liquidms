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
				//let sanitized = subjects[sv].name.replace(/[\x00-\x19\x7F-\xFF]/, '');
				let sanitized = subjects[sv].name.replace(/\%[8-9a-fA-F][0-F]/g, '');
				sanitized = sanitized.replace(/\%[0-1][0-F]/g, '');
				insert.name = decodeURIComponent(sanitized);
			}catch (error) {
				console.error(`URL decode error: `, subjects[sv].name, error);
				let sanitized = subjects[sv].name.replace(/\%20/g, ' ');
				sanitized = sanitized.replace(/\%2F/g, '/');
				sanitized = sanitized.replace(/\%27/g, "'");
				sanitized = sanitized.replace(/\%[0-9A-F][0-9A-F]/g, '.');
				insert.name = sanitized.replace(/\%[0-9A-F][0-9A-F]/g, '+');
				//insert.name = subjects[sv].name;
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

