import NetgameComponent from './NetgameComponent.js';

export default class NetgameListComponent extends HTMLElement {
    constructor(model, data = {}) {
        super();
        let template = document.querySelector('template[data-name="netgamelist"]');
        let templateContent = template.content;
        this.netgames = {};

        const shadowRoot = this.attachShadow({mode: 'open'})
            .appendChild(templateContent.cloneNode(true));

        /* Event handlers */
        this.eHdl_ngcon = (e) => {
            //console.debug("eHdl_ngCon.this:", this);
            this.notifyController.bind(this)();
            //ServerBrowser.eventbus.send("query", Object.keys(this.netgames));
            for (let el in this.netgames) {
                this.netgames[el].notifyController();
            }
            ;
            e.preventDefault();
        }
        this.eHdl_sort = this.render.bind(this);
        this.eHdl_view = this.update.bind(this, {});
        this.eb_conn = function () {
            console.error("No eventbus hook registered yet!", this);
        };
        this.shadowRoot.querySelector('a.checkbox').addEventListener("click", (event) => {
            this.classList.toggle("reverse");
            event.preventDefault();
        });
        this.shadowRoot.querySelector('input[name="search"]').addEventListener("change", this.search.bind(this));

        // Drag & Drop container
        this.addEventListener("dragover", (e) => {
            e.preventDefault();
            let dragged = this.querySelector(".dragging");
            let nextElement = this.getDNDSlot.bind(this)(e.clientX, e.clientY);
            console.debug(nextElement);
            if (nextElement == null) {
                this.appendChild(dragged);
            } else {
                this.insertBefore(dragged, nextElement);
            }
        });
    }

    getDNDSlot(x, y) {
        // Get DND candidates
        let draggables = [...this.querySelectorAll('*[draggable="true"]:not(.dragging)')];
        // Find element closest to pointer
        return draggables.reduce((closest, current, index) => {
            const box = current.getBoundingClientRect();
            const nextBox = draggables[index + 1] && draggables[index + 1].getBoundingClientRect();
            const inRow = y - box.bottom <= 0 && y - box.top >= 0; // check if this is in the same row
            const offsetX = x - (box.left + box.width / 2);
            //console.debug(box, nextBox, inRow, offsetX);
            if (inRow && this.shadowRoot.querySelector('[name="view"]').value == "gallery") {
                if (offsetX < 0 && offsetX > closest.offsetX) {
                    return {
                        offsetX: offsetX,
                        element: current
                    };
                } else {
                    if ( // handle row ends,
                        nextBox && // there is a box after this one.
                        y - nextBox.top <= 0 && // the next is in a new row
                        closest.offsetX === Number.NEGATIVE_INFINITY // we didn't find a fit in the current row.
                    ) {
                        return {
                            offsetX: 0,
                            element: draggableElements[index + 1]
                        };
                    }
                    return closest;
                }
            } else if (this.shadowRoot.querySelector('[name="view"]').value == "list") {
                const box = current.getBoundingClientRect();
                const offsetY = y - box.top - (box.height / 2);
                if (offsetY < 0 && offsetY > closest.offsetY) {
                    return {offsetX: offsetX, offsetY: offsetY, element: current,};
                } else {
                    return closest;
                }
            } else {
                return closest;
            }
        }, {offsetX: Number.NEGATIVE_INFINITY, offsetY: Number.NEGATIVE_INFINITY}).element;
    };

    init() {
        customElements.define('sb-netgamelist', NetgameListComponent);
    }

    notifyController() {
        //console.debug("notifyController.this", this);
        try {
            console.debug("NLC.notifyCtrl(): this = ", this);
            ServerBrowser.netgamecon.fetchServers().then((response) => {
                //console.debug("notifyController.netgamecon.response.this", this);
                console.info("Notify response: ", response);
                this.shadowRoot.querySelector('[name="update"]').classList.remove("error");
                this.shadowRoot.querySelector('[name="update"]').value = "Update all";
                for (let i in response) {
                    this.netgames[i].classList.add("locked");
                    this.netgames[i].updateListener().then();
                }
            }).catch((error) => {
                console.error("Notify error: ", error);
                this.shadowRoot.querySelector('[name="update"]').classList.add("error");
                this.shadowRoot.querySelector('[name="update"]').value = "Update failed!";
            });
        } catch (error) {
            console.error("Error updating netgames(maybe just try again?):", error);
            this.shadowRoot.querySelector('[name="update"]').classList.add("error");
            this.shadowRoot.querySelector('[name="update"]').value = "Update failed!";
        }
        //event.preventDefault();
    }

    async connectToEventbus() {
        try {
            this.eb_conn = await ServerBrowser.eventbus.attach("refresh", this.handleBus.bind(this));
            //.then( this.notifyController.bind(this) );
            this.notifyController();
        } catch (error) {
            if (this.querySelector('[slot="netgames"]') != undefined) {
                this.removeChild(this.querySelector('[slot="netgames"]'));
            }
            console.error("Error connecting to Eventbus (will retry in 2s):", error);
            setTimeout(this.connectToEventbus.bind(this), 2000);
        }
    }

    sort(netgames, sort) {
        let rVal = netgames;
        //console.log(`Sort by: ${sort} (${typeof netgames})`, netgames);

        switch (sort) {
            case "players": {
                //case "minplayers":{
                //sort = "players";
                rVal = rVal.sort((a, b) => {

                    if (a.hasAttribute(sort) && !b.hasAttribute(sort)) {
                        return -1;
                    } else if (!a.hasAttribute(sort) && b.hasAttribute(sort)) {
                        return 1;
                    } else if (!a.hasAttribute(sort) && !b.hasAttribute(sort)) {
                        return 0;
                    }
                    return (Number(a.getAttribute(sort)) - Number(b.getAttribute(sort)));
                });
            }
            case "ping": {
                // Numeric sort
                rVal = rVal.sort((a, b) => {
                    if (a.hasAttribute(sort) && !b.hasAttribute(sort)) {
                        return -1;
                    } else if (!a.hasAttribute(sort) && b.hasAttribute(sort)) {
                        return 1;
                    } else if (!a.hasAttribute(sort) && !b.hasAttribute(sort)) {
                        return 0;
                    }
                    return (Number(b.getAttribute(sort)) - Number(a.getAttribute(sort)));
                });
                break;
            }
            case "updated_at": {
                // Timestamp sort
                rVal = rVal.sort((a, b) => {
                    let aVal = (a.hasAttribute(sort)) ? Date.parse(a.getAttribute(sort)) : -1;
                    let bVal = (b.hasAttribute(sort)) ? Date.parse(b.getAttribute(sort)) : -1;
                    return (bVal - aVal);
                });
                break;
            }
            case "version":
            case "roomname":
            case "origin":
            case "name":
            default: {
                // Lexical sort
                rVal = rVal.sort((a, b) => {
                    if (a.hasAttribute(sort) && !b.hasAttribute(sort)) {
                        return -1;
                    } else if (!a.hasAttribute(sort) && b.hasAttribute(sort)) {
                        return 1;
                    } else if (!a.hasAttribute(sort) && !b.hasAttribute(sort)) {
                        return 0;
                    }
                    return a.getAttribute(sort).localeCompare(b.getAttribute(sort));
                });
                break;
            }
        }
        console.debug("Criteria sorted NetgameList:", rVal);
        // Bury unreachable netgames
        /*
        rVal = rVal.sort( (a,b) => {
            if(a.classList.contains("error") && !b.classList.contains("error")){
                return 1;
            }else if(!a.classList.contains("error") && b.classList.contains("error")){
                return -1;
            }
            return 0;
        });
        */
        console.debug("Availability sorted NetgameList:", rVal);
        return rVal;
    }

    search(e) {
        let results = [];
        for (let candidate in this.netgames) {
            this.netgames[candidate].classList.remove("hidden");
            // TODO: Capsule into NetgameComponent
            this.netgames[candidate].shadowRoot.querySelector("#playerlist").removeAttribute("open");

            console.log("Target value: ", e.target.value);
            if (e.target.value !== "") {
                this.netgames[candidate].classList.add("hidden");
            }
            // Do the attributes match?
            for (let field of this.netgames[candidate].attributes) {

                // Get unformatted representation (RegEx: [ -z])
                let field_parsed = field.value.replace(/\%[8-9a-fA-F][0-F]/g, '');
                field_parsed = field_parsed.replace(/\%[0-1][0-F]/g, '').replace(/\+/g, ' ');
                field_parsed = field_parsed.replace(/[^\x20-\x7E]+/g, '');

                if (field_parsed.match(new RegExp(e.target.value, 'i'))) {
                    results[candidate] = this.netgames[candidate];
                    results[candidate].classList.remove("hidden");
                    break;
                }
            }
            // Does the player name match?
            if (this.netgames[candidate].morph_has(e.target.value)) {
                results[candidate] = this.netgames[candidate];
                results[candidate].classList.remove("hidden");
            }
        }
        console.info("Search results: ", results);
    }

    connectedCallback() {
        this.shadowRoot.querySelector('[name="update"]').addEventListener('click', this.eHdl_ngcon);
        this.shadowRoot.querySelector('[name="sort"]').addEventListener('change', this.eHdl_sort);
        this.shadowRoot.querySelector('[name="view"]').addEventListener('change', this.eHdl_view);
        //this.eHdl_ngcon = setInterval(this.notifyController, 5000)
        this.connectToEventbus();
        this.update();
    }

    disconnectedCallback() {
        this.shadowRoot.querySelector('[name="update"]').removeEventListener('click', eHdl_ngcon);
        this.shadowRoot.querySelector('[name="sort"]').removeEventListener('change', eHdl_sort);
        this.shadowRoot.querySelector('[name="view"]').removeEventListener('change', eHdl_view);
        //clearInterval(this.eHdl_ngcon);
        ServerBrowser.eventbus.detach("refresh", this.eb_conn);
    }

    adoptedCallback() {
        this.render();
    }

    attributesChangedCallback() {
        this.update();
    }

    update(data = {}) {
        //let modelFetch = ServerBrowser.db.servers;
        for (let i in data) {
            if (this.netgames[i] == undefined) {
                let newNG = new NetgameComponent(data[i]);
                newNG.slot = "netgames";
                this.netgames[i] = newNG;
            }
            //this.netgames[i].classList.add("locked");
            //this.netgames[i].updateListener();
            this.netgames[i].update(data[i]).then(() => {
                this.netgames[i].render();
            });
        }
        // Update view type
        console.log("new viewtype: ", this.shadowRoot.querySelector('[name="view"]').value);
        this.setAttribute("view", this.shadowRoot.querySelector('[name="view"]').value);
        //console.log("Updated List: ",this);

        // Accumulate 1s of updates into one render call
        if (this.nextUpdate == undefined) {
            this.nextUpdate = setTimeout(() => {
                this.render();
                this.nextUpdate = undefined;
            }, 1000);
        }
    }

    async render() {
        let sort = this.shadowRoot.querySelector('[name="sort"]').value;
        let netgames_ordered = [];
        for (let i in this.netgames) {
            // Parse netgames into array
            netgames_ordered.push(this.netgames[i]);
        }

        let toRender = this.sort(netgames_ordered, sort);
        for (let i in toRender) {
            this.appendChild(toRender[i]);
        }
        //console.log("Rendered List: ",this);

        /* Accumulate player count */
        let players_total = 0;
        let maxplayers_total = 0;
        this.querySelectorAll('sb-netgame:not(.error)').forEach((e) => {
            console.debug("querySelector routine called")
            players_total += Number(e.getAttribute("players"));
            maxplayers_total += Number(e.getAttribute("maxplayers"));
        });
        this.shadowRoot.querySelector('progress').setAttribute("value", (players_total / maxplayers_total));
        this.shadowRoot.querySelector('span[name="players_total"]').innerText = players_total;
        this.shadowRoot.querySelector('span[name="maxplayers_total"]').innerText = maxplayers_total;
    }

    async handleBus(message, data) {
        switch (message) {
            case "refresh": {
                this.update(data);
                break;
            }
            default: {
                break;
            }
        }
    }
}

