<?php

namespace LampSCryptoGate;

use Shopware\Bundle\AttributeBundle\Service\CrudService;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Models\Payment\Payment;

class LampSCryptoGate extends Plugin
{


    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        /** @var CrudService $crudService */
        $crudService = $this->container->get('shopware_attribute.crud_service');
        $crudService->update('s_order_attributes', 'lampscryptogate_token', 'string');
        $crudService->update('s_order_attributes', 'lampscryptogate_uuid', 'string');
        $crudService->update('s_order_attributes', 'lampscryptogate_url', 'string');
        Shopware()->Models()->generateAttributeModels(array('s_order_attributes'));

        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');

        $options = [
            'name' => 'cryptogate_payment',
            'description' => 'Cryptocurrencies',
            'action' => 'CryptoGatePayment',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
                '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAATcAAABOCAYAAABFeFGPAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4wIEDSEe1+68aAAAABl0RVh0Q29tbWVudABDcmVhdGVkIHdpdGggR0lNUFeBDhcAACAASURBVHja7V15YFTV1f+d+2YSJhu7KARIAlqtW5UEAogSIAvJTEDb0PqVWr+i1SpBURa7qGm1akFQCW6t1v2zhrrAZCELBi1mBRfcqmIWiSyKCMkkIcm8e74/EmImJpPZ3kyC7/cPZGbee/fd5XfPOfcsgA4dOnTo0KFDhw4dOnTo0KFDhw4dOnTo0KFDhw4dOnTo0KFDh45TBBSoB3PWuUFNY1unC5VnsKAzwfJMgCYBCAUwDIAJwHEA3wL4FoQaSPyXBb0LtJVHLP/yG334dOjQMSjI7ejjMcONbfIXILqCgNncSWQecSMY70NgGxG/HHZj3bv6UOpwB+NvKZwohJhChBiApjB4jMPCYP5GQtQToR6KfV/DfSn79F77Dq+++uqIoKCgKObv+k0IIQHUn3baaV/ExsZ2/CDIrXFT9BwQriHgZwBCfC4FArvB9Gj4UdPzlPVhuz71dPTGhDWlkYLtSQzMA2EeGGe4eYsjACoALjEo6ta6+xbW/VD6rrCw8DS73b6AmeMBxAI4E8AYJ5eoRHSAmeuY+R0hxJsGg+E/ycnJX50y5Nb80JRpUuH1YE7w0/t8QeCNoWx4jFbsa9OX9A8b52blBB1rHrGImJaBkAhA+HDlvEPMTxoFPV/zt8Tjp1rfWa3WMUS0FMAvAMT5oO8YQDkz50gptyxatOjAkCS31s3Rk+3M9wB0JQJi1+MPQXRD+PLaN/Ul/sPD1Mz84NZhxhsI+D2AsRo/rhnETxrYeG/d+oRDQ73vtm3bdq6iKGuY+RcAgjR6jJ2IXiaiB1NTUyuGBLkxg2ybo64DaD2AsACPExP46TY23Dx6xb5Gfcn/EMA0YU3xUmK6C8BkPz+8hUAPsj3k7oYHZrUOtZ7bunXrVEVR1gFY7GeBpISZV1kslvcGLbkdfTxmeFAHP8cMyyAbt30CdGVoZs1uffGfuoi6rSDKrhr+CSAhwE2pEUTXf7FuQfFQ6LecnBxTSEjInQBuBhAcoGZIAE8GBwevTkz0nYrvE3I7/vCUqUJKK4CzB+kYthOwLCyz9nmdBk49TFxddDWDNgEIHzQiJPN9+8OMdyArwT5Y+81qtV5MRM8DOGeQNOkLZv6NxWLZMSjIrWnz1HPBahGA8YNeZwHWhGXW3q/TwSmCrFJDpM3+AAjLB2kLSw0dhivqHkw4NgiJ7Xoiegja2dU8luKI6I9paWn3BZTcbI9EX8AqdgIYOVTWAxHdGra8ZqPODEMbY7NKw4Kb7a8BmD/Im7pX6VBT6h9MOTgo9oOsLBEXF7eemW8Z5P32fEtLy7IlS5Z47NrlMbkde2RSjKIquwC3/YUGwkkXDq30f0lMS8NW1LyoU8TQxJg1u8JN3FrAwOwh0uTPlQ51TqAJLicnRzGZTE8R0a+GQqcxc4GiKJenpqZ65Nblke/KofXjQhVV2aoBsQHgl8O+mRwGKc4H+HfoDMHyJQQTP318U1S8ThNDD5Ery0zDZMv2IURsADBFNRgKYtYWDw+kxBYSEvL8UCG2Li1roZQyZ/fu3UZPrjd4clGoKeRxMM7T6I3eoKyddgAf8OPja2ztwdldX3xJwAYmngbGPC+JNUgQvXR8Y+RFw29pOOrL5qdVJqcR4zkvO6HaSB3/8+qMHf3Gz1oqk6IlI5FAseg0CJ8OYAQBHQw0E7CfgX3EVKYAO1+bub2uv3ulVqWeLqT6IoALvbNxULo1fvsuDfdyImPJ08w0a8ixMvGF7RIvIyMnGVuWqP5+fFxc3MYu37WhhvRDhw49BmCZ5uTWuCnqajB+qdn0Be08+f+mdtNFBGno/JxLwjNrH+j+LjtmD8AXe/GoScIY9CSAy306h0FBAHtugyR6waSGX7tl1pbv+UpllGWYWpXGXzPTMmaOpT77r/vfqQASmPhaOwBzRVIFA081t7Y/uzNh54me1+RPzz9k2W1Jk2r7U8RY4rG+DzZqOcsnrtrxJ/aifYMA8ydEjcz6Erjdnw/Nzc29gZlv8tHtVAArANj7USUvI6L/8fEr/MZqte61WCwPubcW3cCx7MnRCsS7ACK8aOhXANoBRPbRnC/DM2u6P7dlx6xk8MbONc9Xhy2vewYAeNPUYBupx9CZPcRLMuXLIzLrXvPVKJgrUy4H8yseXv5w7ozCTFA3R3WSWk6G0jq58Xdg/AnAOC+beIiIsqZNj/9HFmVJB9WFs0R1ZfmTBFztWV9iXl58YakWC3TCqpKZRPwfAMoQ16wlQGkN6xds98fD8vLyYpl5F3xkw2bmdywWy8VOiPRhADdo8Cp2Zr7UYrGUu2x/cufuCpR7vCQ2MHhDeGbtRLudo4lwFYC/A/igc9DlG71+221XUVTRvWgaYb/IF8TWJWk9dGj9uNBAz3gC/b0vYkuvTvlRy6SmCjCyfUBsAHA6Mz+2u7K8fOFbC6c4kBtlybgZM5cB3qrVvsW4VYWhBH72FCC2rjXHz0y6LVdzD4OcnBwTM78IHx7OEdFAzvCxGr2OgYiezcnJCXOjo12U2h6ZFAPwz7zvHfEaAIxcWVcXtrz2ufDM2uvCM2vP7wiiUQob1vb6dZdthT833VTzRTfRCfjyMGBSaHDIygBT2+tNrSdu7E1saeUpV0iVqwmsxYSZrijy7bTK5LTeBKceFdcCKB8sbGCEcjsIU3Hq4DRWg+/R+iGhoaFZgG/7jYiq+vsuPz8/GF7abQfAVJPJ5LL/m8vkJlTlDnh4ANEDx8NvrPmsry9GXVdzPGTFvgYHyU1FAjFWgcQGR9GYfH3SeVMApbcDRupYsjNhp4MNw1yefC0R50Bbr/sIYrxmrky+queHBakFbR0G/BTA4UCzwIQ1pZEgzsQpBgZ+G7mqcIZW9y8oKPiRFr5sUsrqfvVGu/0CaBzCRUTXb9269QKfkVvj42eNoc7UJ16PqS07Jsu2OTqRs84d0DM64ubaT8JW1G4IX17zaK+vIn2sE44JM5muDYjMxnxN71NRS2XKlSA85oEa5onDowGMf5orUxwOVgpjCw8ScGPAdTi2/xUa5AAcFOopifVa3VxKeZ8PhJHeaAkLC/vQCfHM8EO/KYqiPOgzchPtHVf7iJFHgPgOZhTZRrc0ND40xaMj/bBxtZeB6afMqPbZTsoiEOT2snVmUYGDxFaWPJ2Zn3ZxbL7tJEjc02HAeCZ4ulgUsHwhtSz1/J4fWuMLXwaRNVCrP3LljgkMXIlTF3PGr93hc7eWvLy8nzDzYg3a+3ZCQv+xskQU66d+S9i2bdtlA+/aAy56kG0zfqtBA8eSUG8EUOa2tLMEKlDzCjNetW2O/hmAh+C1QzH/uHnz5ItDl9e/7acBsgPy9z0/SN+VHi5F278wQLwfE3LsCm4OUvkcZtrBgtsLY4sOmstTSkD8Rw9lSJMQas7c0rkX9XQVUZhuU8GpCIQx3yCXA/DKvYTBxQLCb7GdzJJANBnAj+FCGn0h5Rp0phnyYRv4Vo1UwqoBfhLnpE2fACgkohU+Et9uBfCGV+TW9PDkeOpMK6xFd/XppNry8JSJKvMzxKhmlvlhR6Pe6nLs7dXZYKB2S8ujkWWq3VgA4HxvWqOyuBKAX8iNmF6wzix2sD9KY1sWGNEDX4tRhbGFBzPKMo61Ko1tYDELANRvqVwZxc3wvDbF2aGm4N8DuPPkB1vjCz4yVyRtAcivDqDTfrvbeBjfeitNH/ky1JgaiMwcY9bsCh+GlrvBAy5my6TbSmK+uG9BjS+ea7VaxwDa+AIyc7+aUn5+foSU8uw+9xfgYQBriegvPmxLmtVqPdNisXzmsVpKUmgXmEzo05tdqjIBzAkMXgOinbbR9V83bYp5oHHzhNF9/T7kdw1fsooMAC3eNQfJ/pr8qqI62A3SqhfEgOHqrjYr48OMoC2ztrSCUQXm8UDnQQCIEiExg8GxRGwB0QvozJflah+sSd6d7CAFs0S2v8nhcPjxBQBGe3mbbYFKOXRk3SVNDeuSbgLwfwMLbzLDh9LVldAo04eUstLJ17F98MkhABaz2ZxpsVhaAEz3YXOEEMKps/DAdh1B87SS4KGInf2ssEt7fTICxDcTB33UvHlynw6EETfXfgIecCINhPOaNk0d64e5vyd/erFDxS6yK6vhugHY0NbUdDYAcIdhUe7Mwm6JNXfG9vLcWYVVefFFe6wzinJzZ2xfSuC1brRtmFHFKgcbzqyiMgCf+JMcmLxf8Mx4BQGGSrjbhXWa4cNH/lwrvk5PT691Qny9VdIXOjo6zjGbzXlAZ9A+gIt8LEn+3GNy442RJjDP1Gj6fhT+u8+/6pv10J+x8DTJ4oX+FwTneS1LQs7xg1L6cs+/MkrnhoGwdICLygG+G4IWkCFo5Lb47XsBIG9O3rcDqngzZm0E8LEb287VC/MXBjv2Lf7tT2oj8jqjc6OpraMk0OR2cF3ixwCODrBKp0XdVhDl7bO2b98+CoAmCSGcqaRd358ktyPMnGE2m5defvnl3bbO0NDQH8P3pQfOKSgo+FG/EoCzK5uDDZdB+iYSoI8F3qcxsOXRyAmq3anj4dmNj581JuK6T4/0/kKRyn4ppLfD+GNA2x2fpWMkRotp2GICOx14wXL1tpnFb/X1nbky+SpiLGJgGgghDJRKFtcWxBc0Ap2OueaK5CK4nnF1lGG0uhBAz7C0QgB/9AchRK4tOQ/Saek4l8xP+7JTB0sFtAFddOzSOBtAnVdSoqougHYHP1UDqMNxAPKI6Bqz2XyoD8nuV0S+L80gpZzTn1bhXA2S+JGGA96nSirtQZfB0VG/N2zhB8cfAz79/rUCXqeUIUFTNJXZgA4Thr/j+BknDazeiKTUqtTPSarndJi4qujCouYe6tcVABadFHsJWKIwvw/0VImY3AklloyUnuTW3NJWHW4K7mAvTy9d6iPJl7K3SaKJ3tQ6xCmoWaj7slOdFh8an2UNQfPARN0VhfKCl9LVdM3GhKhfya24uHj4iRMn7rZYLP/o/V1XzdPHNXJNATPPBvCE2+QmiSYSa6R3qHiz72fyxc6eyeC7+jo57fpuurd7A0vWNMyHgff7yPhxqQuTfzVJ9XYAZGzFI3B0sN3ZTW7fXdCdViejLMN0ghrT2K2xJAf1fGfCzhOWiqT/AnQ+NAZD/GSADc6VWf+4VIMf17KdJ4ZhJwYoSCOaQi6DkAYXXjrOB02K0+pdjUZjv+TWVdTle8RmtVpTOzo6noAmeR+78ZN++97p9GYfRwJ8d+ePw26u7TO0J2J57SoVMqYrqD6bCFYAhQw8I5nMEZl16/qcG1kQBPY+FRNBY8mNP+r5d9J7SaEAJrlwpQldohcxmR3eXcidjkIXnra1nuhOD9VCjbOZcRD9pKnpZ2KcmfFhRpCjIE818AOYebAWGuqNgc0XQroksbBv3K1+rNF71rlTLb64uHh4bm7u00SUpzGxAU5iZw3OO5zDSYsShiydOt+NyKyvBVALN7JTNI+OygR8kkBztJYjISEc7BHGdkTD3XTvhElp1Qti8uJKagBgetzsvXsqy99kRgUb+Lm8uKIPev48b2ZhCYASS1nS2Uy0BTRwPzFgbGn5NhJAD0LjL/1R0pKGRpA8S1U6T5WVlSXQjHQX7zcGWaUGT11XrFZriIZzt8qNdsxva2v7p2sbtk8QlpeXd3paWtoht8iNQNoEk5Nzz2K3ZlgOlObDMSsY7Ks4vSDOgdIZBeF7CJaO3vIdiPAk2buQhoSTxNOVl23AcBTrrKL/plen/Eyq/CFcMTyrYqRj2+kY+6dcrzuL9ASIrmGW7Z5NRfoLPCpJydUHNibvd/aLic2XzGbw6a4O6eRjbWPrAU/rLEyCdjvPgOTWRa73ElEm/FvUGQDGotOnznVyA5EE+9zoxiT6JjfbpuirmDiEobwfHjSiiq7b0+HsRk2bpo61HVJLQHyBL9uHj8BajQILcngnoWCYR13Mci6AJ7uFBM4Se6orpjFjLrG8gEGnM/A1wBvy4ov2nPzdtrjtn5jLk3eCBq4aJaAYHTuGOqBd1wDozN0Gdw4tGPkN6xd4ZIifmpkfcWKY8VkPt6mXB2ZOvsKd7pIGGg0PyY2Zx2lxGukKueXl5c3oiocOiDlBShnWj2nF6cTRIibvk7Ab6w71zXq4HaBHCXKXrf3o4cbs6OeasqfM436OzsJX7PuaDPgVgM982L5WyoLUaiCIe7nWsOJRVAWDHCS1quqyS1hyFZjXMWgpgAUEXEmgki67Xs9GuBRiRuBebWOT5iqpUQ1xrx/wkqfPOhFsSIenSU8V+ysD7eFdp9huzA1FYvChxWg09jtf8vPz5zHzWwhgQXYhhPvkRuTzylMA9e3f1pw9cTwcjYMjCVgKyB22zdHvNW6e3KczcdgNtXtVyOS+xFIP8Y2mhhqwg8qlEnlaoGZiWvWCmO77HlEq0Xf42YhhTeyRi0ybgb/utVw1r94UZoM7vmnNdsg8L+aiZ978TO813Jeyz9lPIte8Hueu3Uka7e0YRCCi/UIIS3JycrMTqcmMAGdIJiLVfckNjpPbR0Lkzr7FaqMzm9H5xKLUtilmQV9fjsisr2XQH3zEPvUaD8WEnn+FhoXWwY1TTIc7qUp3nxWkFrQx8Nb3pS9s2XZJ8YFen7oSddJcOK3wkOMkEuO1nqjBo20nXOcYth6+v/+F5wxdZfYSPZwkr7pgNnC78JAB7HEcrKIo7GPCeLa9vf2C1NTU1wf4aRwCjxa3yU0yfezjRrDo4Df6/mJAg3gwEz/Sr5TRYvw34P0hAHee0mpHbeCzev695dwt7WD818MZON9xMLnHRKQaMN16wHDEwT3GUpk0D67U/CS81zvtOZjP0XqWfpi1pB0Mm0vqCITHKmmbxCJ4mqNQccEFhNxTSQHAJsM91hpUVfWVxmFj5svT0tJ+3TN8qi+UlpYaAFyMAIOZm/veLJzujPIj8up4jD5iyGcAOpc6Gb4kdGX9wX5aeJkLNzyTN0aa6JaG75W9G7v2k6am7Oij6Dw58Vx/J+zVWHI7O+m9pNCeEQbodMI9z4NRvdRxM5JbCYZ2CN6RO3373t7kZC5PsjDT03DlNIsdN6HF78wdYW/T1gewBzE0uGLDYeZNkauLN3j4lLEetu3Thr8lvu9UJV1bfD4kznLzxt8eWXdJk6ddZjKZGtrafBJt9pLFYnGpGpzNZjuXiEIGAbk1uE1u7c3BHw0LaVc91akZvDEis+7JATvpwehxDJdCvRr6IraeWp7XHSXlHk0HAjAOa8UsAMXfCWDSyhDL3bzVETC29vwgb2bJx+gVIJ/FWaK6qnwhMW4EkAJXj+lJOGTgtZ8ImgPyzxE/g/cTyBUD9UT/ryQXpDYVV7jfU+yVOSQxMfF4bm7u195u7kTkzuY+HYHHEbPZ/K3baunYtZ80AR6n8v4mPKjtRZdEagNHYaDMCQCY6c7+vuuszuV1rv32EyeG7dZ6NFQWC3v+PeyLETsAHHCDIOtiZ8wclzuzsM/CKem70sMzcjIUAKiuLH+SGLkAFrpMbKCa3OnbK3p9luqv2SqIPsQgBUnSRCWFl0HzXfjUB1JQt1Q6UCEWP6YVd9befg92XMgfRoUAe5JG5SW67oBLbg7Dl9dVMmOs7ZHJZ7NKMwiYDqIYEMaA0UTENazy8xE31fdb8FfYlfleyxWM/3QRurYLhOSSuaVz15yseLVlyRbVXJGyGWCXyr0RMKpnQeXUqtTTFaHYrbHWI+aK5Bsl2rJPTGq/FMAuED5z1zWNGA/1VGmn7Z5mhN2jBesRJPhtN4VEBnE2pGjVVlvmpv0b5jvd/CauKjyPAbf9LonpbR8s9D1ENNube7S3t+/Nz88fq6rqo0T0BYBbnDwvTkPfOlfxgcfkxqQWEos7PXjonKbsmCwQvx4mlUpasa/N+YIHA/Un1aqn3ScM/K/3pEN5/hkPmhBuCk4DvlMrTxjsjwyzK7fCNe/8CEtlyjIp5UwimgOpnsVS/gHAvUT8MTMRwPMA7ALwH/cWMA7CaHTIsjDePiadgdP8R2602007SGVX1tuAgwXd7JGfs8JVPpi/5QC8qVFwyGg0zpFSPkZE44io30y3OTk5JiI6L9D9LYR43WNyC7+xvsK2OfozuB/Yez7A54Nxp43U1qZN0WUglErmHcNX1FX48gUbs6MWA/A2qaad0PGSH8flpp7kVhJbcjytIukPBHIpkwUzP+G4a/I8APe2DUNlcCs6AMwH8BcCGtF5iqy4SCxr8mKtLY4bHG7RODDBAQfXJX4cubq4HsBkFxn5lUHAa5iwqmQmmK/2RGBqZVOZt89XVbVUURQJeBLQBwA4jYi63Vzsdnu/Jqng4OAJgNdhlNGAV4dUbLfbPSc3InDjJvyDCOu8aISpK9xnviC6Cj0OD44+HjN85MGaJk+jAo4/PGUqSfmE97seCkKX7z/gr4XAQIKlbOFl1lkF3RMkbsasJ3ZXlC9xJTSqD8xamL8wmE7QBIZ6AMAsc0XyQTBOd6NRuXnx2x1CmVLLklPAmOVvoiDwdgZd55pKJwJOblG3FUTZVX4JHhy+EfCmNyelJ7Fo0aLDubm5lV5s9D1J8dv09PTPnTxrHzz2E+xEbm7uE16S295FixYdduVl+u98o3gGnhX87WskHU5jgjrkRtvo6IO27Kh/ctZct4rIHt8UkyakfAs+yIZALB/1vwojN540/AOdAfBSUZaSZ/GFIcoo2SBY/aRL4jEAbhAbUG8U9qt72toyPswIEgIbA8QXrhEW03v718//PKAS2+qin9hVw054enrbQ1ryek4x+yQdPBFVE5Gm8npX9l5vrncaU+wSmYT/7vOvmjbHPAXm67x+I+koyjJTEoDTQJTQMwll46ZoCxESCVQrSdYIKZol2ESCQwGcxUyWruylviDcPaHL6wsCsC4uPjHp+EoA95/8IH96/qH0ipQUgN9kuJ1Z2NPU3EeZ1YWvxu9wcARtbWxaA8I5gSCM/aFlJZHNswdWTUkGTGqLXFlmYmPLzcR8OwBP427buMP+Lx8261kA98DLIuoD1UzwFoWFhaEdHR3e5J9rI6KnvSY3AFCI/qp22hO86jQhZLdtoWnz1HPBaiQASHbMzCsErmWGhcEgJjB1Rc93ORX78oxGSrozUAuEQXelViWW9KyGtS1++15LVUoimPPA0LQaFwEHmbAwL77EwT/OUpUSx5JvD5g4lJUlsbronwD92Xn7qWHSrYXT/DpmJCKZMB9ovoIYE7y83csND6Qc9VXbLBbLkdzc3FcBeFVnlpk1dYmy2+3T3OGfPrAlNTX1a5+QW8iNn++3ZUdtZNDvvWhQS8iR6L3oCt8kVhf2kHu7yY1zoNgOY46fpurrw1fU5iFwGCak2GLZbZlpjbV2F72xTt9eba5InA2If8MD1wIXsUdh+tlr8dvren6YvDv5DLbzK9Co/qXLI2MQD5OdVwEI739zwJMsBIYqJMQGX9+TiO5h5iXw/GAB8Ny/1VXy9DYmdUAzklsvHxrUfjeR586GDHzdNKp+Jj8VNazzb17YY0S6XRZavpp8IYARfphbbWCZOQjm+FTY23MzSuc6pG7JjS/+zCQj4gHeBPgueSYBHUT4m3pUzH5tpiOxLX5n7gijHQWAVinmXceX9y74BkyP4FQFccGB9fPf9vVt09LS3odj5TK3u95isXyp8dvP8IK8rWazecDTZbfEQrruQEtT9pRlgCz2ZFcgYDIIb9ps1Na0Kbq6xwseilhe8+l3JKjMhR98DxiUFbHii48GwzxnYEarKTh/YcVC88mSfADQVUzmptSqxKeEKu4CIc0LrZwJeBUCd1inF34vCiC5LHmU2oZ8ABcOlvXPRqwnO5YBXpf6G2xQSfIazSRCKe8QQljgWbWy0Nzc3GKN39/T0K0OwLFouBO+cR+27Kh7vFRPvzfQAKoAKiLit5hxK4BkrdXRsHF1Sb5OJ26uTLkczJ4buRkfqFIsLphd0OcJYFp10nlCpauZkQFyOV9YLRhbiPkp66yi//Z93wUxpCp58CLpIAPz8uILS309UpGrSpaB+AmcUqCHG9YvWK7lE6xW671EdNsp1WtEG9PS0m7VjNw4a66haVS9lQgpQ7SPGmAQ0/qreO8N0ssTZ0sSWV7RLuGowvYV2+J3HHb6rLLkqZJ4BhH9iIEJ1GWbYqCJGIcg6ANSuXrbrEKniRUtuxdMgl25nwHv6nwSVuXOKHxPg42IItfs2Nk7C8oQRu0JMl3oC982Z8jJyTGFhIS8D/gpm4v2xLa/vb39goFSMXlFbgDwzaapEUGk7gJw/hDro2MEujQss+Z96BgyGH9L4UShiHegcXUyP8AO8IKG9Ulv+ONhXfUN3oCXXg6DAB1CiITU1NS3XL3A49OU0Sv2NYKV+QDeHUIdZGMIs05sQw8HNibvJ+LfAP4MBNNA+gB+7y9iA4C0tLRKACtPgSlwszvE5hW5AZ0FWux2TgBQMQQ65ziTTIrI/PwtnSqGJvavS9oGDF0bEgHP7l+/YIO/n2s2mx9lHro2S2Z+zmw2u31q7rWD0MiVdcfaWUkGUekgnlS1kOKSiOX15TpFDG00rF+wjl3wcRqEyD/t+MhrAAqI5Nna2no9gBeGYL9tOeOMM5Z5uO59xK45UGyHYu4A8R8R4Go4vVDEQcZfRlz36RGdGk4VME1YU3w/Md0yRES2PHSEZjQ8MKs1kM0oLS012Gy2fxHRT4eIxPZ/YWFhv05ISPCsgJKvG9SUHTUXEM8DPCHAfdNCoD+FLq95kGho22l09I3INUVrwXTfIG/mi+OOj/z1nr/HdgyGxpSWlhpaWlqymfn6Qd5vT7e0tFyzZMkSj121NEmj2bh5wmjioDsBXA/PnAi93SpftdvlLSNX1tXpFHBqY+KakhuY+cHAzDOnkAD/uSG07G5kZQ26YstWq/UmIlo/CPvNTkRr0tLSHvBeYNYQxx+eMlWwzAIjA36JU+TXAeWv4Zmfv64v+x8SwRXFMtNzIc12BQAAAiVJREFUCGDV8144Aimuatgwv2Aw91t+fn68lPJFAFGDpEm1RHR1Wlram76xBvgBzQ9MPkMa6LcA/QZuVuF2ATYGXmNG9vAVtVX6Uv9hIiqrdJjdpmaBeDV8cFDm+YKiLe2sLj98f/JXQ6HfcnJywkJDQ+9m5uUInK1cAnispaVl7ZIlS2y+Gws/ghnUsjlmGkNewRDJAF8Az9KeHGSgiMAFYUHtVlcL0ej4AUhxt5bEMfFdIK3D976Hd0ngtv1/Sywciv2Wm5t7DoA/A8jw86N3CCFWp6amvuP7jSaAOLR+XGhosCkWgs4D4wwGIqkze+x3OwijGeADEDgEKT5QDfZ3R9zwRY2+jHU4w6S1xZdIib8ASNBWVKM9JHH//rBdOYPRtuaJqqqq6ioiWqyhJCcBWAFsMJvN/9FsaPRloONURuRt26dCKksh8UsQpvrotl+D8BognmhYN/+UNIVs3bp1vBBiKRH9HMBFPuKK9wH8S0r5Ynp6eq32JgIdOn5IKqvCc5lxCXUWUXE1y/E3IK4Ci2oJWXQgtKz8VJDSXEVeXt7pABKllDOEENOZeQqAUQNc1ojOMp17mXmXqqqlixcv3u/PduvkpuOHq7reljvSrpqiBWQ0iB2kOmbUECn1HdJeN1QOB/ws2YUrijJZCDFcSmnq1NCpnZm/ZuavLRaL7jSvQ4cOHTp06NChQ4cOHTp06NChQ4cOHTp06NChQ4cOHQHB/wPFwhD01wy5QgAAAABJRU5ErkJggg=="/>'
                . '<div id="payment_desc">'
                . '  Pay now securely and encrypted with Bitcoin, Bitcoin Cash, Dash or Litecoin.'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);

        $options = [
            'name' => 'cryptogate_payment_btc',
            'description' => 'Cryptocurrency Bitcoin',
            'action' => 'CryptoGatePayment',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
                '<img src="data:image/png;base64,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"/>'
                . '<div id="payment_desc">'
                . '  Pay securely and encrypted with Bitcoin now.'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);

        $options = [
            'name' => 'cryptogate_payment_ltc',
            'description' => 'Cryptocurrency Litecoin',
            'action' => 'CryptoGatePayment',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
                '<img src="data:image/png;base64,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"/>'
                . '<div id="payment_desc">'
                . '  Pay securely and encrypted with Litecoin now.'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);

        $options = [
            'name' => 'cryptogate_payment_bch',
            'description' => 'Cryptocurrency Bitcoin Cash',
            'action' => 'CryptoGatePayment',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
                '<img src="data:image/png;base64,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"/>'
                . '<div id="payment_desc">'
                . '  Pay securely and encrypted with Bitcoin Cash now.'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);


        $options = [
            'name' => 'cryptogate_payment_dash',
            'description' => 'Cryptocurrency Dash',
            'action' => 'CryptoGatePayment',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
                '<img src="data:image/png;base64,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"/>'
                . '<div id="payment_desc">'
                . '  Pay securely and encrypted with Dash now.'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);

    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context) {
        /** @var CrudService $crudService */
        $crudService = $this->container->get('shopware_attribute.crud_service');
        $crudService->delete('s_order_attributes', 'lampscryptogate_token');
        $crudService->delete('s_order_attributes', 'lampscryptogate_uuid');
        $crudService->delete('s_order_attributes', 'lampscryptogate_url');
        Shopware()->Models()->generateAttributeModels(array('s_order_attributes'));

        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
        if(!$context->keepUserData()){
            $folder = $this->container->getParameter('kernel.root_dir') . '/' . $this->getPath() . '/Resources/snippets/' ;
            $databaseLoader = $this->container->get('shopware.snippet_database_handler');
            $databaseLoader->removeFromDatabase($folder);
        }
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), true);
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    public function update(UpdateContext $updateContext) {
        $currentVersion = $updateContext->getCurrentVersion();
        $updateVersion = $updateContext->getUpdateVersion();

        if (version_compare($currentVersion, '1.2.0', '<=')) {
            /** @var CrudService $crudService */
            $crudService = $this->container->get('shopware_attribute.crud_service');
            $crudService->update('s_order_attributes', 'lampscryptogate_token', 'string');
            $crudService->update('s_order_attributes', 'lampscryptogate_uuid', 'string');
            $crudService->update('s_order_attributes', 'lampscryptogate_url', 'string');
            Shopware()->Models()->generateAttributeModels(array('s_order_attributes'));
        }

        $this->checkIntegration();
    }

    private function getVersion() {
        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->container->get('kernel')->getPlugins()['LampSCryptoGate'];
        $filename = $plugin->getPath() . '/plugin.xml';
        $xml = simplexml_load_file($filename);
        return (string)$xml->version;
    }

    private function checkIntegration() {
        $api_url = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'api_url');
        $api_key = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'api_token');

        if(empty($api_url) || empty($api_key)){
            return;
        }

        try {
            /**
             * @var $service \LampSCryptoGate\Components\CryptoGatePayment\CryptoGatePaymentService
             */
            $service = $this->container->get('crypto_gate.crypto_gate_payment_service');

            $paymentDataParams =  [
                'amount' => 1.00,
                'currency' => "EUR",
                'first_name' => "first_name",
                'last_name' => "last_name",
                'payment_id' => 42,
                'email' => "test@example.com",
                'return_url' => "__not_set__",
                'callback_url' => "__not_set__",
                'ipn_url' => "__not_set__",
                'cancel_url' => "__not_set__",
                'seller_name' => Shopware()->Config()->get('company'),
                'memo' => ''.$_SERVER['SERVER_NAME']
            ];

            $paymentData = $service->createPayment($paymentDataParams, $this->getVersion());
        } catch (\Exception $e) {

        }
    }
    /**
     * @param Payment[] $payments
     * @param $active bool
     */
    private function setActiveFlag($payments, $active)
    {
        $em = $this->container->get('models');

        foreach ($payments as $payment) {
            $payment->setActive($active);
        }
        $em->flush();
    }

    public static function getSubscribedEvents() {
        return [
            'Enlight_Controller_Action_PreDispatch_Frontend' => ['onFrontend',-100],
        ];
    }

    public function onFrontend(\Enlight_Event_EventArgs $args)
    {
        $subject = $args->get('subject');
        $request = $subject->Request();
        $view = $subject->View();

        if ($request->getControllerName() === 'checkout' && $request->getActionName() === 'cart') {
            $error = $request->has('CouldNotConnectToCryptoGate') ? (int) $request->get('CouldNotConnectToCryptoGate') : null;

            if ($error) {
                $view->assign('PaymentError', "error");
            }

        }

        $this->container->get('template')->addTemplateDir(
            $this->getPath() . '/Resources/views/'
        );
    }





}
