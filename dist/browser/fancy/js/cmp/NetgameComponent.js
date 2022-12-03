export default class NetgameComponent extends HTMLElement{
	static get observedAttributes(){
		return [
			'hostname',
			'port',
			'name',
			'version',
			'roomname',
			'origin',
		];
	}

	constructor(data = {}){
		super();

		this.api = this.observedAttributes

		let template = document.querySelector('template[data-name="netgame"]');
		let templateContent = template.content;

		this.eb_conn = function(){console.error("No eventbus hook registered yet!", this);};

		const shadowRoot = this.attachShadow({mode: 'open'})
		  .appendChild(templateContent.cloneNode(true));

		this.updateListener =  this.notifyController.bind(this);
		//this.updateListener = this.shadowRoot.querySelector('[name="update"]').addEventListener("click",));
		this.update(data);
		//this.classList.add('locked');
		this.updateListener();
	}

	init(){
		customElements.define('sb-netgame', NetgameComponent);
	}

	async connectToEventbus(){
		try{
			this.eb_conn = await ServerBrowser.eventbus.attach("query", this.handleBus.bind(this));
		}catch(error){
			console.error("Error connecting to Eventbus (will retry in 2s):", error);
			setTimeout(this.connectToEventbus.bind(this), 2000);
		}
	}

	connectedCallback(){
		this.shadowRoot.querySelector('[name="update"]').addEventListener("click", this.updateListener);
		this.connectToEventbus();
		//this.updateListener();
		this.update(); 
		this.render();
	}
	disconnectedCallback(){
		ServerBrowser.eventbus.detach("query", this.eb_conn);
		this.shadowRoot.querySelector('[name="update"]').removeEventListener("click", this.updateListener);
	}
	adoptedCallback(){
		this.render();
	}
	attributeChangedCallback(name, oldVal, newVal){
		if( this.isConnected && oldVal != newVal){
			console.info(`(${this.hostname}) NETGAME UPDATE ATTRIBUTE ${name}: ${oldVal} -> ${newVal}`);
			//let tmp_o = {};
			//tmp_o[name] = newVal;
			//this.update(tmp_o);
			this.render();
		}
	}

	update(data = {}){
		if(Object.entries(data).length > 0){
			for(let i in data){
				//console.log("Update attrib", i);
				if(i == "players_list"){
					this.playerlist = data[i];
					continue;
				}
				this.setAttribute(i, data[i]);
				//console.log(i, data[i]);
			}
		}
		//console.log("Updated Netgame: ",this);
	}

	async render(){
		this.innerHTML = ''
		for(let i of this.getAttributeNames()){
			// Skip the nasty ones
			//if(!this.shadowRoot.querySelector(`[name="${i}"]`)){continue;}
			this.shadowRoot.querySelectorAll(`[name="${i}"]`).forEach( (e) => { e.innerHTML = this.getAttribute(`${i}`); });
		}

		// Player count update
		this.shadowRoot.querySelector('progress').setAttribute("value", (this.getAttribute('players') / this.getAttribute('maxplayers')) );


		for(let i in this.playerlist){
			//if(i == "players_list"){continue;}
			let player = document.createElement('details');
			player.classList.add('player');
			player.classList.add(this.playerlist[i].team);

			player.slot = "players_list";
			let timespan = new Date(this.playerlist[i].seconds * 1000).toISOString().substr(11, 8);
			player.innerHTML = `
			<summary>${this.playerlist[i].name}</summary>
			<ul>
			<li>Team: ${this.playerlist[i].team}</li>
			<li>Score: ${this.playerlist[i].score}</li>
			<li>Online: ${timespan}</li>
			</ul>`;
			this.appendChild(player);
		}
		//console.log("Rendered Netgame: ",this);
	}

	notifyController(e = undefined){
		console.info("Updating netgame ", this.getAttribute("hostname"), this);
		if( this.getAttribute("hostname") &&
			this.getAttribute("port") ){
			let attrs = {};
			for(var i = this.attributes.length - 1; i >= 0; i--) {
				attrs[this.attributes[i].name] = this.attributes[i].value;
			}
			if(!this.classList.contains("locked")){
				console.info("Issuing request to:", this.attributes, this.classList.contains("locked"));
			    this.classList.add("locked");
				ServerBrowser.netgamecon.updateOne(attrs)
				  .then( () => {
					  this.classList.remove("locked");
					  this.classList.remove("error");
				  })
				  .catch( () => { 
					  this.classList.remove("locked");
					  this.classList.add("error")
				  });
			}
			//this.classList.add("locked");
		}
		if(e != undefined){ e.preventDefault(); }
	}

	morph_has( input = undefined ){
		// Return existence of input and highlight it within the component
		
		// Fields within meta details to look for
		let metafields = [
			"level_name",
			"level_md5",
			"origin",
			"roomname",
		];

		this.shadowRoot.querySelector("#playerlist").removeAttribute("open");
		this.shadowRoot.querySelector("#meta").removeAttribute("open");

		for(let field of metafields){
			if(
				input &&
				this.getAttribute(field) &&
				this.getAttribute(field).match(new RegExp(input, 'i'))
			){
				console.log('Found matching field \"', field, 'within',  this.attributes.name);
				this.shadowRoot.querySelector("#meta").setAttribute("open", "");
				return true;
			}
		}
		
		if(
			typeof(this.playerlist) == "object" &&
			input &&
			this.playerlist.filter((i) => { return i.name.match(new RegExp(input, 'i')); }).length > 0
		){
			this.shadowRoot.querySelector("#playerlist").setAttribute("open", "");
			return true;
		}
		return false;
	}

	handleBus(message, data = {}){
		switch(message){
			case "query":{
				for(let sv in data){
					//if(data[sv].hostname == this.getAttribute("hostname")){ console.log("Caught refresh on: ", this.getAttribute("hostname")); }
					if(data[sv] == this.getAttribute("hostname")){
						console.log("Caught refresh on: ", this.getAttribute("hostname"));
						this.notifyController();
					}
				}
				break;
			}
			default:{
				break;
			}
		}
	}
};

