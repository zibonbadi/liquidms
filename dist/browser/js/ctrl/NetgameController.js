export default class NetgameController{
	constructor(){
		this.fetchServers();
	}

	async fetchServers(url = '/liquidms/snitch'){
		ServerBrowser.req.get(url)
			.then( (response) => {
				let toModel = [];
				let servers = this.CSVToArray(response, ',');
				for(let sv in servers){
					let insert = {};
					insert.hostname = servers[sv][0];
					insert.port = servers[sv][1];
					insert.name = servers[sv][2];
					insert.version = servers[sv][3];
					insert.roomname = servers[sv][4];
					insert.origin = servers[sv][5];
					toModel[insert.hostname] = insert;
				}
				ServerBrowser.db.insert(toModel);
			}).catch( (error) => {
				console.error('Failed to update NetgameModel: ', error, this);
				throw error;
			});
	}

	CSVToArray(str, delim = ','){
		let rows = str.slice(str.indexOf("\n") + 1).split("\n");
		let vals = [];
		for( let i in rows ){
			vals.push(rows[i].split(delim));
		}
		console.log(vals);
		return vals;
	}

	async updateOne(server){
		console.log("Update target:", server);
		ServerBrowser.req.get(`/liquidms/SRB2Query/?hostname=${server.hostname}&port=${server.port}`)
			.then( (response) => {
				let query = JSON.parse(response, ',');
				ServerBrowser.db.populateOne(server.hostname, query);
			}).catch( (error) => {
				console.error('Failed to update NetgameModel: ', server.hostname, error);
				throw error;
			});
	}

}


