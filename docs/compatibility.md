# Nubit compatibility policy

`nubit-compatibility.json` is the machine-readable compatibility declaration
for this template. A Nubit `0.x` minor is treated as a release line: packages
inside one frontend or backend line are installed lockstep.

The template CI verifies the pinned stable combination. The scheduled canary
also compares the executable grid fixtures from `nubit-symfony` and
`nubit-react`, then builds this frontend against the declared release line.

| Channel | Purpose | Failure meaning |
| --- | --- | --- |
| stable | Locked dependencies committed here | Template regression |
| latest | Fresh resolution within declared lines | Release drift |
| source | Current sibling repository contracts | Upcoming incompatibility |

Changing a protocol requires a new protocol URI. Changing only fixtures within
that protocol is allowed when it clarifies existing behaviour and both adapters
pass the same cases.
