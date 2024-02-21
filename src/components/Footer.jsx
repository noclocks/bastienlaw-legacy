import React from 'react';
import { Link } from "react-router-dom";

class Footer extends React.Component {
    render() {
        return (
            <footer>
                <p className="text-center hidden-sm">
                    <Link to="/" exact>
                        Home
                    </Link>{" "}
                    | <Link to="/about">About</Link> |{" "}
                    | <Link to="/services">Services</Link>{" "}
                    | <Link to="/contact">Contact</Link>
                </p>
            </footer>
        );
    }
}

export default Footer;


/* Old Structure:
<div className="et_builder_inner_content et_pb_gutters1">
<div className="et_pb_section et_pb_section_0_tb_footer fl-short-form-section et_pb_with_background et_section_regular">
    <div className="et_pb_row et_pb_row_0_tb_footer">
        <div className="et_pb_column et_pb_column_4_4 et_pb_column_0_tb_footer  et_pb_css_mix_blend_mode_passthrough et-last-child">
            <div className="et_pb_module et_pb_text et_pb_text_0_tb_footer fl-short-form  et_pb_text_align_center et_pb_bg_layout_light">
                <div className="et_pb_text_inner">
                    <h2>How Can I Help You?</h2>
                </div>
            </div>
            <div className="et_pb_module et_pb_text et_pb_text_1_tb_footer fl-darklinks fl-short-form  et_pb_text_align_left et_pb_bg_layout_dark">
                <div className="et_pb_text_inner">
                    <div
                        id="nf_tp_module_0"
                        className="nf_tp_module"
                        data-form_id={2}
                        data-nf_tp_num={0}
                        data-redirect_id={0}
                    />
                </div>
            </div>
        </div>
    </div>
</div>
<div className="et_pb_section et_pb_section_1_tb_footer et_pb_with_background et_section_regular">
    <div className="et_pb_row et_pb_row_1_tb_footer et_pb_equal_columns">
        <div className="et_pb_column et_pb_column_4_4 et_pb_column_1_tb_footer  et_pb_css_mix_blend_mode_passthrough et-last-child">
            <div className="et_pb_module et_pb_text et_pb_text_2_tb_footer  et_pb_text_align_left et_pb_bg_layout_light">
                <div className="et_pb_text_inner">
                    <h2>Contact The Firm</h2>
                </div>
            </div>
        </div>
    </div>
    <div
        id="contactform"
        className="et_pb_row et_pb_row_2_tb_footer et_pb_equal_columns"
    >
        <div className="et_pb_column et_pb_column_1_3 et_pb_column_2_tb_footer  et_pb_css_mix_blend_mode_passthrough">
            <div className="et_pb_module et_pb_text et_pb_text_3_tb_footer fl-margin-bottom  et_pb_text_align_center et_pb_bg_layout_light">
                <div className="et_pb_text_inner">
                    <h3>Office Location</h3>
                    <span className="nap-item nap-item--address">
                        200 Ashford Center N
                        <br />
                        Ste 215
                        <br />
                        Atlanta, GA 30338
                    </span>
                </div>
            </div>
        </div>
        <div className="et_pb_with_border et_pb_column_1_3 et_pb_column et_pb_column_3_tb_footer  et_pb_css_mix_blend_mode_passthrough">
            <div className="et_pb_module et_pb_image et_pb_image_0_tb_footer">
                <a href="../index.htm" data-wpel-link="internal">
                    <span className="et_pb_image_wrap ">
                        <img
                            decoding="async"
                            src="../wp-content/uploads/sites/1302270/2021/06/logo.jpg"
                            alt="Law Offices of Villard Bastien, LLC"
                            title=""
                            width={500}
                            height={111}
                            loading="lazy"
                        />
                    </span>
                </a>
            </div>
        </div>
        <div className="et_pb_column et_pb_column_1_3 et_pb_column_4_tb_footer  et_pb_css_mix_blend_mode_passthrough et-last-child">
            <div className="et_pb_module et_pb_text et_pb_text_4_tb_footer fl-darklinks fl-margin-bottom  et_pb_text_align_center et_pb_bg_layout_light">
                <div className="et_pb_text_inner">
                    <h3>Contact Us</h3>
                    <p>
                        Phone:
                        <span className="nap-item nap-item--phone">
                            <a href="tel:+1-404-865-1952" data-wpel-link="internal">
                                404-865-1952
                            </a>
                        </span>
                        <br />
                        Fax:
                        <span className="nap-item nap-item--phone nap-fax">
                            470-275-3906
                        </span>
                    </p>
                </div>
            </div>
            <div className="et_pb_button_module_wrapper et_pb_button_1_tb_footer_wrapper et_pb_button_alignment_center et_pb_module ">
                <a
                    className="et_pb_button et_pb_button_1_tb_footer et_pb_bg_layout_dark"
                    href="../atlanta-medical-malpractice-law-office/index.htm"
                    data-wpel-link="internal"
                >
                    Atlanta Office
                </a>
            </div>
        </div>
    </div>
    <div className="et_pb_row et_pb_row_3_tb_footer">
        <div className="et_pb_column et_pb_column_4_4 et_pb_column_5_tb_footer  et_pb_css_mix_blend_mode_passthrough et-last-child">
            <div className="et_pb_module et_pb_text et_pb_text_5_tb_footer fl-darklinks fl-margin-bottom  et_pb_text_align_left et_pb_bg_layout_light">
                <div className="et_pb_text_inner">
                    <div className="default-footer">
                        <div className="flfooterbrand">
                            <p style={{ textAlign: "center" }}>
                                © 2024
                                <a
                                    href="https://lawyers.findlaw.com/profile/view/4438170_1"
                                    target="_blank"
                                    data-wpel-link="external"
                                    rel="noopener noreferrer"
                                >
                                    Law Offices of Villard Bastien, LLC
                                </a>{" "}
                                • All Rights Reserved
                            </p>
                        </div>
                        <div className="flfooterlink">
                            <p style={{ textAlign: "center" }}>
                                <a
                                    href="../disclaimer/index.htm"
                                    data-wpel-link="internal"
                                >
                                    Disclaimer
                                </a>
                                &nbsp;|
                                <a
                                    href="../site-map/index.htm"
                                    data-wpel-link="internal"
                                >
                                    Site Map
                                </a>
                                &nbsp;|
                                <a
                                    href="../privacy/index.htm"
                                    data-wpel-link="internal"
                                >
                                    Privacy Policy
                                </a>{" "}
                                |
                                <a
                                    href="https://www.lawyermarketing.com/"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    data-wpel-link="external"
                                >
                                    Business Development Solutions
                                </a>{" "}
                                by
                                <a
                                    href="https://www.findlaw.com/"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    data-wpel-link="external"
                                >
                                    FindLaw
                                </a>
                                , part of Thomson Reuters
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
*/
