import { Icon, Sidebar, Card, Heading } from "../../components";
import { __ } from '@wordpress/i18n';

const Homepage = () => {
    const cardLists = [
        {
            iconSvg: <Icon icon="site" />,
            heading: __('Site Identity', 'the-conference'),
            buttonText: __('Customize', 'the-conference'),
            buttonUrl: cw_dashboard.custom_logo
        },
        {
            iconSvg: <Icon icon="colorsetting" />,
            heading: __("Color Settings", 'the-conference'),
            buttonText: __('Customize', 'the-conference'),
            buttonUrl: cw_dashboard.colors
        },
        {
            iconSvg: <Icon icon="frontpagesetting" />,
            heading: __("Front Page Settings", 'the-conference-pro'),
            buttonText: __('Customize', 'the-conference-pro'),
            buttonUrl: cw_dashboard.front
        },
        {
            iconSvg: <Icon icon="layoutsetting" />,
            heading: __("Layout Settings", 'the-conference'),
            buttonText: __('Customize', 'the-conference'),
            buttonUrl: cw_dashboard.layout
        },
        {
            iconSvg: <Icon icon="generalsetting" />,
            heading: __("General Settings"),
            buttonText: __('Customize', 'the-conference'),
            buttonUrl: cw_dashboard.general
        },
        {
            iconSvg: <Icon icon="footersetting" />,
            heading: __('Footer Settings', 'the-conference'),
            buttonText: __('Customize', 'the-conference'),
            buttonUrl: cw_dashboard.footer
        }
    ];

    const proSettings = [
        {
            heading: __('Header Layouts', 'the-conference'),
            para: __('Choose from different unique header layouts.', 'the-conference'),
            buttonText: __('Learn More', 'the-conference'),
            buttonUrl: cw_dashboard?.get_pro
        },
        {
            heading: __('Multiple Layouts', 'the-conference'),
            para: __('Choose layouts for blogs, banners, posts and more.', 'the-conference'),
            buttonText: __('Learn More', 'the-conference'),
            buttonUrl: cw_dashboard?.get_pro
        },
        {
            heading: __('Multiple Sidebar', 'the-conference'),
            para: __('Set different sidebars for posts and pages.', 'the-conference'),
            buttonText: "Learn More",
            buttonUrl: cw_dashboard?.get_pro
        },
        {
            para: __('Boost your website performance with ease.', 'the-conference'),
            heading: __('Performance Settings', 'the-conference'),
            buttonText: __('Learn More', 'the-conference'),
            buttonUrl: cw_dashboard?.get_pro
        },
        {
            para: __('Choose typography for different heading tags.', 'the-conference'),
            heading: __('Typography Settings', 'the-conference'),
            buttonText: __('Learn More', 'the-conference'),
            buttonUrl: cw_dashboard?.get_pro
        },
        {
            para: __('Import the demo content to kickstart your site.', 'the-conference'),
            heading: __('One Click Demo Import', 'the-conference'),
            buttonText: __('Learn More', 'the-conference'),
            buttonUrl: cw_dashboard?.get_pro
        }
    ];

    const sidebarSettings = [
        {
            heading: __('We Value Your Feedback!', 'the-conference'),
            icon: "star",
            para: __("Your review helps us improve and assists others in making informed choices. Share your thoughts today!", 'the-conference'),
            imageurl: <Icon icon="review" />,
            buttonText: __('Leave a Review', 'the-conference'),
            buttonUrl: cw_dashboard.review
        },
        {
            heading: __('Knowledge Base', 'the-conference'),
            para: __("Need help using our theme? Visit our well-organized Knowledge Base!", 'the-conference'),
            imageurl: <Icon icon="documentation" />,
            buttonText: __('Explore', 'the-conference'),
            buttonUrl: cw_dashboard.docmentation
        },
        {
            heading: __('Need Assistance? ', 'the-conference'),
            para: __("If you need help or have any questions, don't hesitate to contact our support team. We're here to assist you!", 'the-conference'),
            imageurl: <Icon icon="supportTwo" />,
            buttonText: __('Submit a Ticket', 'the-conference'),
            buttonUrl: cw_dashboard.support
        }
    ];

    return (
        <>
            <div className="customizer-settings">
                <div className="cw-customizer">
                    <div className="video-section">
                        <div className="cw-settings">
                            <h2>{__('The Conference Tutorial', 'the-conference')}</h2>
                        </div>
                        <iframe src="https://www.youtube.com/embed/2vGQeDvNGJI?si=oV4b4xZ_9NYKVfy0" title={__( 'How To Create An Event/Promotion/Conference Website In 2021 | The conference Tutorial', 'the-conference' )} frameBorder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerPolicy="strict-origin-when-cross-origin" allowFullScreen></iframe>
                    </div>
                    <Heading
                        heading={__( 'Quick Customizer Settings', 'the-conference' )}
                        buttonText={__( 'Go To Customizer', 'the-conference' )}
                        buttonUrl={cw_dashboard?.customizer_url}
                        openInNewTab={true}
                    />
                    <Card
                        cardList={cardLists}
                        cardPlace='customizer'
                        cardCol='three-col'
                    />
                    <Heading
                        heading={__( 'More features with Pro version', 'the-conference' )}
                        buttonText={__( 'Go To Customizer', 'the-conference' )}
                        buttonUrl={cw_dashboard?.customizer_url}
                        openInNewTab={true}
                    />
                    <Card
                        cardList={proSettings}
                        cardPlace='cw-pro'
                        cardCol='two-col'
                    />
                    <div className="cw-button">
                        <a href={cw_dashboard?.get_pro} target="_blank" className="cw-button-btn primary-btn long-button">{__('Learn more about the Pro version', 'the-conference')}</a>
                    </div>
                </div>
                <Sidebar sidebarSettings={sidebarSettings} openInNewTab={true} />
            </div>
        </>
    );
}

export default Homepage;