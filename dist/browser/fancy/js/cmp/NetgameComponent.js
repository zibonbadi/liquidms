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

		/* color lookup table */
		this.textcolors = [
			"var(--netgame-text-white)",
			"var(--netgame-text-purple)",
			"var(--netgame-text-yellow)",
			"var(--netgame-text-green)",
			"var(--netgame-text-blue)",
			"var(--netgame-text-red)",
			"var(--netgame-text-gray)",
			"var(--netgame-text-orange)",
			"var(--netgame-text-sky)",
			"var(--netgame-text-lavender)",
			"var(--netgame-text-gold)",
			"var(--netgame-text-lime)",
			"var(--netgame-text-steel)",
			"var(--netgame-text-pink)",
			"var(--netgame-text-brown)",
			"var(--netgame-text-peach)",
		];

		this.api = this.observedAttributes

		let template = document.querySelector('template[data-name="netgame"]');
		let templateContent = template.content;

		this.eb_conn = function(){console.error("No eventbus hook registered yet!", this);};

		const shadowRoot = this.attachShadow({mode: 'open'})
		  .appendChild(templateContent.cloneNode(true));

		this.updateListener =  this.notifyController.bind(this);
		//this.updateListener = this.shadowRoot.querySelector('[name="update"]').addEventListener("click",));
		//this.classList.add('locked');
		this.update(data).then( () => {
			this.updateListener();
		});
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
		this.update().then( () => {
			this.netgames[i].render();
		});
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

	color_text(input){
		try{
			let out = "";
			let spans = 0;
			let ccodes = [];
			//let sanitized = input.replace(/\%[8-9a-fA-F][0-F]/g, '');
			//sanitized = sanitized.replace(/\%[0-1][0-F]/g, '').replace(/\+/g,' ');
			// String == char array
			for(let c of input){
				let c_code = c.charCodeAt(0);
				ccodes.push(c_code);
				if(c_code >= 0x20 && c_code < 0x80){
					out += c;
				}else if(c_code >= 0x80 && c_code <= 0x8F){
					if(spans > 0){
						out += "</span>";
						spans--;
					}
					out += `<span style="color: ${this.textcolors[c_code - 0x80]};">`;
					spans++;
				}
			}
			//console.debug("Successfully parsed netgame name: ", spans, ccodes);
			for(spans; spans > 0; spans--){
				out += "</span>";
				spans--;
			}
			out = out.replace(/\%20/g, ' ');
			out = out.replace(/\%2F/g, '/');
			out = out.replace(/\%27/g, "'");
			out = out.replace(/\%[0-9A-F][0-9A-F]/g, '.');
			return out;
		}catch(error) {
			console.warn("Unable to parse text colors! Fallback to sanitize_text()", error);
			return this.sanitize_text(input);
		}
	};

	sanitize_text(input){
		try{
			let sanitized = input.replace(/\%[8-9a-fA-F][0-F]/g, '');
			sanitized = sanitized.replace(/\%[0-1][0-F]/g, '').replace(/\+/g,' ');
			return decodeURIComponent(sanitized);
		}catch (error) {
			console.error(`URL decode error: `, input, error);
			let sanitized = input.replace(/\%20/g, ' ');
			sanitized = sanitized.replace(/\%2F/g, '/');
			sanitized = sanitized.replace(/\%27/g, "'");
			sanitized = sanitized.replace(/\%[0-9A-F][0-9A-F]/g, '.');
			return sanitized.replace(/\%[0-9A-F][0-9A-F]/g, '+');
		}
	}


	async update(data = {}){
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
			this.shadowRoot.querySelectorAll(`[name="${i}"]`).forEach( (e) => {
				if(e.getAttribute("name") == "name"){
					console.debug("Selected element attrib: ", e, e.getAttribute("name"));
					e.innerHTML = this.color_text(this.getAttribute(`${i}`)); 
				}else{
					e.innerHTML = this.getAttribute(`${i}`); 
				}
				});
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

