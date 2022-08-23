![LiquidMS logo](../liquidMS.svg)

Fancy server browser
====================

The fancy server browser is a fully-fledged web application employing a
Model-View-Controller mechanism for user interactions and state updates.
Below you can find a listing of all files and an explanation on how they
relate with one another in order to facilitate this architecture.

`dist/browser/fancy/favicon.svg`
: LiquidMS logo favicon

`dist/browser/fancy/index.php`
: Entry point. Relies on custom elements & shadow DOM.

`dist/browser/fancy/css/button.css`
: Styling for interactive elements

`dist/browser/fancy/css/Netgame(List)Component(-shadow).css`
: Styling for web components. The general CSS files define it's styling in
  context of the larger overall webpage while the `*-shadow.css` files are
  reserved for styling within thier respective shadow DOMs.

`dist/browser/fancy/css/main.css`
: General styling

`dist/browser/fancy/img/bg.svg`
: That yellow background with the pretty shapes.

`dist/browser/fancy/img/logo.svg`
: LiquidMS logo.

`dist/browser/fancy/js/main.js`
: Initializer and entry point. Takes care of call dependencies
  by enforcing a staged, but asynchronous load order.
  All global objects are stored within the `ServerBrowser` object.

`dist/browser/fancy/js/cmp/NetgameComponent.js`
: Netgame web component. Represents an individual netgame in the UI.

`dist/browser/fancy/js/cmp/NetgameListComponent.js`
: Netgame list web component. Manages NetgameComponent instances and UI elements

`dist/browser/fancy/js/ctrl/Eventbus.js`
: Global messaging system all components can subscribe to. Used for state and component updates.

`dist/browser/fancy/js/ctrl/NetgameController.js`
: Manages netgame-related user calls.

`dist/browser/fancy/js/ctrl/RequestController.js`
: Fetch API abstraction class, differentiating between successful and failed HTTP requests.

`dist/browser/fancy/js/mdl/NetgameModel.js`
: Client-side netgame cache/database.

