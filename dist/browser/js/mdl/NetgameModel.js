export default class NetgameModel{
	constructor(){
		this.servers = [];
	}

	async insert(data = []){
		async function sleep(ms){ return new Promise((res) => setTimeout(res, ms)); };

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
			try{
				//let sanitized = sv.name.replace(/[\x00-\x19\x7F-\xFF]/, '');
				let sanitized = sv.name.replace(/\%[8-9a-fA-F][0-F]/g, '');
				sanitized = sanitized.replace(/\%[0-1][0-F]/g, '').replace(/\+/g,' ');
				insert.name = decodeURIComponent(sanitized);
			}catch (error) {
				console.error(`URL decode error: `, sv.name, error);
				let sanitized = sv.name.replace(/\%20/g, ' ');
				sanitized = sanitized.replace(/\%2F/g, '/');
				sanitized = sanitized.replace(/\%27/g, "'");
				sanitized = sanitized.replace(/\%[0-9A-F][0-9A-F]/g, '.');
				insert.name = sanitized.replace(/\%[0-9A-F][0-9A-F]/g, '+');
				//insert.name = sv.name;
			}
			insert.version = sv.version;
			insert.roomname = sv.roomname;
			insert.origin = sv.origin;
			//insert.updated_at = new Date().toLocaleString();

			// this.servers for all , toRefresh for update culling
			this.servers[insert.hostname] = insert;
			toRefresh[insert.hostname] = insert;
					
			// Offset request flood
			let passthru = [];
			passthru[insert.hostname] = insert;
			ServerBrowser.eventbus.send("refresh", passthru);
			await sleep(250);
		}
		//ServerBrowser.eventbus.send("refresh", toRefresh);
	}

	async populateOne(hostname, data = {}){

			// Sanitization & flattener block
			this.servers[hostname].cheats = data.cheats;
			this.servers[hostname].dedicated = data.dedicated;
			this.servers[hostname].gametype = data.gametype;
			if(data.level){
				this.servers[hostname].level_md5 = data.level.md5sum;
				this.servers[hostname].level_name = data.level.title;
			}
			this.servers[hostname].maxplayers = data.players.max;
			this.servers[hostname].modified = data.mods;
			this.servers[hostname].players = data.players.list.length;
			this.servers[hostname].players_list = data.players.list;
			this.servers[hostname].version_major = data.version.major;
			this.servers[hostname].version_minor = data.version.minor;
			this.servers[hostname].version_name = data.version.name;
			this.servers[hostname].version_patch = data.version.patch;
			this.servers[hostname].updated_at = new Date().toLocaleString();

			
			let toRefresh = {};
			toRefresh[hostname] = this.servers[hostname];
			console.info("Populated entry:", toRefresh[hostname]);
			ServerBrowser.eventbus.send("refresh", toRefresh);
	}

}

