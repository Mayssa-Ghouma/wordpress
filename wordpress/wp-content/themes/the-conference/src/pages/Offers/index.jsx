import { __ } from "@wordpress/i18n"
import { themeClub, themeInstallation, gdpr, seo, pluginsSetup, vipSupport } from "../../components/images"

const Offers = () => {

    const offerBannerLists = [
        {
            image: themeClub,
            imageUrl: cw_dashboard.theme_club_upgrade,
            title: __("Theme Club", "the-conference"),
        },
    ]

    const offerCardLists = [
        {
            image: themeInstallation,
            imageUrl: cw_dashboard.theme_install,
            title: __("Theme Installation & Setup", "the-conference"),
        },
        {
            image: gdpr,
            imageUrl: cw_dashboard.gdpr_setup,
            title: __("GDPR Compliance", "the-conference"),
        },
        {
            image: seo,
            imageUrl: cw_dashboard.seo_setup,
            title: __("Must Have SEO Setup", "the-conference"),
        },
        {
            image: pluginsSetup,
            imageUrl: cw_dashboard.plugin_setup,
            title: __("Must Have Plugins Setup", "the-conference"),
        },
        {
            image: vipSupport,
            imageUrl: cw_dashboard.vip_support,
            title: __("VIP Support", "the-conference"),
        },
    ]


    return (
        <>
            <div className="cw-offer">
                <div className="banner-section">
                    {offerBannerLists.map((banner, index) => (
                        <a className="image-link" href={banner.imageUrl} key={index} target="_blank">
                            <img src={banner.image} alt={banner.title} />
                        </a>
                    ))}
                </div>
                <div className="card-section">
                    {offerCardLists.map((card, index) => (
                        <a className="image-link" href={card.imageUrl} key={index} target="_blank">
                            <img src={card.image} alt={card.title} />
                        </a>
                    ))}
                </div>
            </div>
        </>
    )
}

export default Offers;