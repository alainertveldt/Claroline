import React from 'react'

import {Button} from '#/main/app/action/components/button'
import {SSO_BUTTON} from '#/main/authentication/buttons/sso'

import {constants} from '#/main/authentication/sso/twitter/constants'

const TwitterButton = props =>
  <Button
    {...props}
    type={SSO_BUTTON}
    icon={constants.SERVICE_ICON}
    service={constants.SERVICE_NAME}
  />

export {
  TwitterButton
}
