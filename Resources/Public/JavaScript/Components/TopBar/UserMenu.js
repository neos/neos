import { isNil } from '../../Helper'
import {ApiService} from '../../Services'
import { RestoreButton } from '../../Templates/RestoreButton'

const BASE_PATH = '/neos/impersonate/'
export default class UserMenu {
    constructor(_root) {
        const userMenuElement = document.querySelector('.neos-user-menu[data-csrf-token]')
        this._csrfToken = !isNil(userMenuElement)
            ? userMenuElement.getAttribute('data-csrf-token')
            : ''
        this._basePath = this._getBasePath(userMenuElement)
        this._root = _root
        this._apiService = new ApiService(this._basePath, this._csrfToken)

        if (!isNil(_root)) {
            this._checkImpersonateStatus()
        }
    }

    _getBasePath(element) {
        let url = BASE_PATH

        if (!isNil(element) && element.hasAttribute('data-module-basepath')) {
            let moduleBasePath = element.getAttribute('data-module-basepath')
            moduleBasePath += moduleBasePath.endsWith('/') ? '' : '/'
            url = moduleBasePath + 'impersonate/'
        }

        return url
    }
    _renderRestoreButton(user) {
        const userMenuDropDown = this._root.querySelector('.neos-dropdown-menu')
        if (isNil(userMenuDropDown) || isNil(user)) {
            return false
        }

        // append restore button to the user menu
        const restoreListItem = document.createElement('li')
        restoreListItem.innerHTML = RestoreButton(user)
        userMenuDropDown.appendChild(restoreListItem)

        // add event listener for restore api call
        const restoreButtonElement = userMenuDropDown.querySelector(
            '.restore-user'
        )
        if (!isNil(restoreButtonElement)) {
            restoreButtonElement.addEventListener(
                'click',
                this._restoreUser.bind(this)
            )
        }
    }

    _checkImpersonateStatus() {
        const response = this._apiService.callStatus()
        response
            .then((data) => {
                const { origin, status } = data
                if (status && !isNil(origin)) {
                    this._renderRestoreButton(origin)
                }
            })
            .catch(function (error) {
                // error occured but we just don`t render the restore button
            })
    }

    _restoreUser(event) {
        event.preventDefault()
        const button = event.currentTarget
        if (isNil(button)) {
            return false
        }

        const response = this._apiService.callRestore()
        response
            .then((data) => {
                const { origin, impersonate, status } = data
                const message = window.NeosCMS.I18n.translate(
                    'impersonate.success.restoreUser',
                    'Switched back from {0} to the orginal user {1}.',
                    'Neos.Neos',
                    'Main',
                    {
                        0: impersonate.accountIdentifier,
                        1: origin.accountIdentifier,
                    }
                )
                window.NeosCMS.Notification.ok(message)

                // load default backend, so we don't need to care for the module permissions.
                // because in not every neos version the users have by default the content module or user module
                window.location.pathname = '/neos'
            })
            .catch(function (error) {
                if (window.NeosCMS) {
                    const message = window.NeosCMS.I18n.translate(
                        'impersonate.error.restoreUser',
                        'Could not switch back to the original user.',
                        'Neos.Neos'
                    )
                    window.NeosCMS.Notification.error(message)
                }
            })
    }
}
