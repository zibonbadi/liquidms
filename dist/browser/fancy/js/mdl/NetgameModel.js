export default class NetgameModel {
    constructor() {
        this.servers = [];
    }

    async insert(data = []) {
        async function sleep(ms) {
            return new Promise((res) => setTimeout(res, ms));
        };

        let subjects = [];
        let toRefresh = [];
        console.debug("NetgameModel: Bulk update...")
        switch (typeof (data)) {
            case "array":
            case "object": {
                // Janky JavaScript, bleh
                if (Array.isArray(data)) {
                    subjects = data;
                } else {
                    subjects = [data];
                }
                break;
            }
            default: {
                throw "Invalid data provided to NetgameModel!";
                break;
            }
        }
        ;
        for (let sv of subjects) {
            let valid = true;
            for (let field in sv) {
                if (typeof (sv[field]) == "undefined") {
                    valid = false;
                }
            }
            if (!valid) {
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
            ServerBrowser.eventbus.send("refresh", passthru);
            await sleep(25);
        }
        //ServerBrowser.eventbus.send("refresh", toRefresh);
    };

    async populateOne(hostkey, data = {}) {

        // Sanitization & flattener block
        this.servers[hostkey].cheats = data.cheats;
        this.servers[hostkey].dedicated = data.dedicated;
        this.servers[hostkey].gametype = data.gametype;
        this.servers[hostkey].name = data.servername;
        if (data.level) {
            this.servers[hostkey].level_md5 = data.level.md5sum;
            this.servers[hostkey].level_name = data.level.title;
        }
        this.servers[hostkey].maxplayers = data.players.max;
        this.servers[hostkey].modified = data.mods;
        this.servers[hostkey].players = data.players.list.length;
        this.servers[hostkey].players_list = data.players.list;
        this.servers[hostkey].version_major = data.version.major;
        this.servers[hostkey].version_minor = data.version.minor;
        this.servers[hostkey].version_name = data.version.name;
        this.servers[hostkey].version_patch = data.version.patch;
        this.servers[hostkey].updated_at = new Date().toLocaleString();


        let toRefresh = {};
        toRefresh[hostkey] = this.servers[hostkey];
        console.info("Populated entry:", toRefresh[hostkey]);
        ServerBrowser.eventbus.send("refresh", toRefresh);
    };
}

