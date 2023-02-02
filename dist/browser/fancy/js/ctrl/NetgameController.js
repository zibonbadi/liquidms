export default class NetgameController {
    constructor() {
        //this.fetchServers();
    }

    async fetchServers(url = '/liquidms/snitch') {
        //console.debug("Fetching servers from ", url);
        ServerBrowser.req.get(url)
            .then(async (response) => {
                //console.debug("Fetch response: ", response);
                let toModel = [];
                let servers = this.CSVToArray(response, ',');
                //console.debug("Parsed fetch response: ", servers);
                for (let sv of servers) {
                    let insert = {};
                    insert.hostname = sv[0];
                    insert.port = sv[1];
                    insert.name = sv[2];
                    insert.version = sv[3];
                    insert.roomname = sv[4];
                    insert.origin = sv[5];
                    //toModel[insert.hostname] = insert;
                    toModel.push(insert);
                    //ServerBrowser.db.insert([insert]);
                }
                ServerBrowser.db.insert(toModel);
                return toModel;
            }).catch((error) => {
            console.error('Failed to update NetgameModel: ', error, this);
            throw error;
        });
    }

    CSVToArray(str, delim = ',') {
        let rows = str.slice(str.indexOf("\n") + 1).split("\n");
        let vals = [];
        for (let i in rows) {
            vals.push(rows[i].split(delim));
        }
        return vals;
    }

    async updateOne(server) {
        return ServerBrowser.req.get(`/liquidms/SRB2Query/?hostname=${server.hostname}&port=${server.port}`)
            .then((response) => {
                let query = JSON.parse(response, ',');
                let hostkey = `${server.hostname}:${server.port}`;
                ServerBrowser.db.populateOne(hostkey, query);
            }).catch((error) => {
                console.error('Failed to update NetgameModel: ', server.hostname, error);
                throw error;
            });
    }

}


