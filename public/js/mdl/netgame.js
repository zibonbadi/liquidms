export default class NetgameModel{
	constructor(){
		this.servers = [];
	}

	async insert(data = []){
		if(typeof(data) != "array"){ throw "Invalid data provided to NetgameModel!"; };
		for(let sv in data){
			let insert = {};
			let toRefresh = [];
			insert.hostname = data[sv][0];
			insert.port = data[sv][1];
			try{
				insert.name = decodeURIComponent(servers[sv][2]);
			}catch (error) {
				insert.name = servers[sv][2];
			}
			insert.version = data[sv][3];
			insert.roomname = data[sv][4];
			insert.origin = data[sv][5];
			// this.servers for all data, toRefresh for update culling
			this.servers[insert.hostname] = insert;
			toRefresh[insert.hostname] = insert;
		}
		ServerBrowser.eventbus.send("refresh", toRefresh);
	}

}

