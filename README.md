# caspio-rest-api-lib

A shell library showing how to interface with the Caspio REST API.

NOTE:
* TokenManager class uses Wordpress-specific methods for storing and retrieving the access token. These will need to be adapted for use in other environments. Search for the methods get_option and update_option.
* The classes also reference a global $logger var that will need to be replaced
