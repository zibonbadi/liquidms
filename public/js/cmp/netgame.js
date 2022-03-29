export default class NetgameComponent extends HTMLElement{
	constructor(model, data = {}){
		super();
		this.dataset.hostname = undefined;

    let template = document.querySelector('template[data-id="netgame"]');
    let templateContent = template.content;
    this.tags = undefined;

		const shadowRoot = this.attachShadow({mode: 'open'})
		  .appendChild(templateContent.cloneNode(true));

		this.addEventListener('click', this.handleEvent);
	}

	connectedCallback(){ update(); render(); }
	disconnectedCallback(){}
	adoptedCallback(){ render(); }
	attributesChangedCallback(){ update(); render(); }

	update(){
		let modelFetch = model.servers[this.dataset.hostname];
		if(modelFetch != undefined){ this.data = modelFetch; }
	}

	render(){
		for(let i in this.data){
		this.createNode
		}
		this.data = kek;
	}
}
